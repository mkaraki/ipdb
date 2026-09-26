// /atk/admin/ignorelist/* -- networks that are never recorded as attackers.
const AUTH = {
  user: Cypress.expose("managerUser"),
  pass: Cypress.expose("managerPass"),
};
const IP = "203.0.113.50";
const BLOCKED = "203.0.113.51";
const IPV6_NET = "2001:db8:99::";

const post = (url, body, options = {}) =>
  cy.request({ method: "POST", url, form: true, body, auth: AUTH, failOnStatusCode: false, ...options });

// Removes every ignore-list entry whose network matches, so specs start clean.
const clearIgnore = (network) =>
  cy.ignoreListIdsFor(network).then((ids) => ids.forEach((id) => post("/atk/admin/ignorelist/delete", { id })));

const add = (network, cidr, description) =>
  post("/atk/admin/ignorelist/add", { network, cidr, description }, { followRedirect: false });

describe("Ignore list management", () => {
  beforeEach(() => {
    cy.asManager();
    cy.visit("/atk/admin/ignorelist/");
  });

  it("renders the add form with a default /32 mask", () => {
    cy.get('form[action="add"] #ip').should("have.attr", "name", "network");
    cy.get("#cidr").should("have.attr", "name", "cidr").and("have.value", "32");
    cy.get("#cidr").should("have.attr", "min", "0").and("have.attr", "max", "128");
    cy.get("#description").should("have.attr", "name", "description");
    cy.get("#description").should("not.have.attr", "required");
    cy.contains('input[type="submit"]', "Add to Ignore List").should("exist");
    cy.contains("h2", "Current Ignore List").should("be.visible");
    cy.get("table thead th").should(
      "have.text",
      ["Network", "Description", "Action"].join("")
    );
  });

  it("shows the calling IP and whether it is ignored", () => {
    // The header reports the client address. Read it, then confirm the
    // ignore-list page agrees. A plain variable is used rather than an alias
    // because cy.visit() clears the alias store; the assertions live inside the
    // .then() because should() args are captured when the queue is built.
    let text = "";
    cy.visit("/");
    cy.get("header code")
      .invoke("text")
      .then((t) => {
        text = t.trim();
        cy.visit("/atk/admin/ignorelist/");
        cy.contains("dt", "Your IP").next().should("have.text", text).and("not.be.empty");
        // Private callers cannot be added to the list (validateIpIsPublic), and
        // no spec adds a 0.0.0.0/0 entry, so the answer is always No here.
        cy.contains("dt", "Is ignored").next().should("have.text", "No");
      });
  });

  it("rejects an invalid or non-public network", () => {
    ["notanip", "", "10.0.0.0", "192.168.0.0"].forEach((network) => {
      add(network, "32", "bad").then((res) => {
        expect(res.status, `network=${JSON.stringify(network)}`).to.eq(400);
        expect(res.body).to.eq("Invalid network address.");
      });
    });
  });

  it("rejects a CIDR outside the address family", () => {
    add(IP, "33", "too wide for v4").then((res) => {
      expect(res.status).to.eq(400);
      expect(res.body).to.eq("Invalid CIDR value.");
    });
    add(IP, "notanumber", "nope").then((res) => {
      expect(res.status).to.eq(400);
      expect(res.body).to.eq("Invalid CIDR value.");
    });
    add(IPV6_NET, "129", "too wide for v6").then((res) => {
      expect(res.status).to.eq(400);
      expect(res.body).to.eq("Invalid CIDR value.");
    });
  });

  it("adds and displays an IPv4 entry", () => {
    clearIgnore(IP);
    add(IP, "32", "e2e allow list").its("status").should("eq", 303);
    cy.reload();

    cy.contains("table tbody tr", "203.0.113.50/32").should("have.length", 1).within(() => {
      cy.get("td").eq(1).should("have.text", "e2e allow list");
      cy.get('input[name="id"]').should("exist");
      cy.contains('input[type="submit"]', "Remove").should("exist");
    });
  });

  it("adds and displays an IPv6 entry", () => {
    clearIgnore(IPV6_NET);
    add(IPV6_NET, "48", "e2e v6 allow list").its("status").should("eq", 303);
    cy.reload();
    cy.contains("table tbody tr", "2001:db8:99::/48").should("exist");
  });

  it("removes an entry after the confirmation is accepted", () => {
    clearIgnore(IP);
    add(IP, "32", "temporary").its("status").should("eq", 303);
    cy.reload();
    cy.contains("table tbody tr", "203.0.113.50/32").should("exist");

    cy.on("window:confirm", () => true);
    cy.contains("table tbody tr", "203.0.113.50/32").find('input[value="Remove"]').click();
    cy.location("pathname").should("eq", "/atk/admin/ignorelist/");
    cy.contains("table tbody tr", "203.0.113.50/32").should("not.exist");
  });

  it("rejects a non-numeric entry id", () => {
    post("/atk/admin/ignorelist/delete", { id: "abc" }).then((res) => {
      expect(res.status).to.eq(400);
      expect(res.body).to.eq("Invalid ID value.");
    });
  });
});

describe("Ignore list effect on reporting", () => {
  beforeEach(() => {
    cy.unlistIp(IP);
    cy.unlistIp(BLOCKED);
    clearIgnore(IP);
  });

  it("silently drops a reported IP that is on the ignore list", () => {
    add(IP, "32", "must not be recorded").its("status").should("eq", 303);

    // The endpoint still answers 200; the IP simply never reaches atkIps.
    cy.postIpAsBot(IP).its("status").should("eq", 200);
    cy.ipInAtkList(IP).should("eq", false);
    // Other rows may share the /24, so assert on the ATKdb section, not the
    // whole page -- a populated neighbour list still renders "found in
    // following databases".
    cy.visit(`/info?q=${IP}`);
    cy.contains("h2", "ATKdb").should("not.exist");
  });

  it("records the IP again once the entry is removed", () => {
    add(IP, "32", "temporary").its("status").should("eq", 303);
    cy.postIpAsBot(IP).its("status").should("eq", 200);
    cy.ipInAtkList(IP).should("eq", false);

    clearIgnore(IP);
    cy.postIpAsBot(IP).its("status").should("eq", 200);
    cy.ipInAtkList(IP).should("eq", true);
  });

  it("ignores every address inside a covered subnet", () => {
    add("203.0.113.0", "24", "whole block").its("status").should("eq", 303);
    cy.postIpAsBot(BLOCKED).its("status").should("eq", 200);
    cy.ipInAtkList(BLOCKED).should("eq", false);
    clearIgnore("203.0.113.0");
  });

  it("ignores IPv6 addresses covered by an IPv6 entry", () => {
    clearIgnore(IPV6_NET);
    add(IPV6_NET, "64", "v6 block").its("status").should("eq", 303);

    const inside = "2001:db8:99::1234";
    cy.unlistIp(inside);
    cy.postIpAsBot(inside).its("status").should("eq", 200);
    cy.ipInAtkList(inside).should("eq", false);

    // A v4 entry must not shadow v6, and vice versa.
    const outside = "2001:db8:98::1";
    cy.unlistIp(outside);
    cy.postIpAsBot(outside).its("status").should("eq", 200);
    cy.ipInAtkList(outside).should("eq", true);
    clearIgnore(IPV6_NET);
  });
});
