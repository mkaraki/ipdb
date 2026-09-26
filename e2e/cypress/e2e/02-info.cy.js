// GET /info?q=  -- IP lookup result page.
const V4 = "203.0.113.10";
const V6 = "2001:db8::10";

// /info answers 400 for bad input, and the body is the whole message.
const visitInfo = (q) =>
  cy.visit(`/info?q=${q}`, { failOnStatusCode: false });
const mainText = () => cy.get("main").invoke("text").then((t) => t.trim());

describe("IP info page", () => {
  it("rejects a request with no q parameter", () => {
    cy.request({ url: "/info", failOnStatusCode: false }).then((res) => {
      expect(res.status).to.eq(400);
      expect(res.body).to.eq("Missing IP parameter.");
    });
  });

  it("rejects private IPv4 ranges", () => {
    ["10.0.0.1", "172.16.0.1", "192.168.0.1"].forEach((ip) => {
      visitInfo(ip);
      mainText().should("eq", `${ip} is a private IP address.`);
    });
  });

  it("rejects private IPv6 ranges", () => {
    visitInfo("fd00::1");
    mainText().should("eq", "fd00::1 is a private IP address.");
  });

  it("marks the page noindex", () => {
    visitInfo("192.0.2.1");
    cy.get('meta[name="robots"]').should("have.attr", "content", "noindex, follow");
  });

  it("shows the ATKdb and reverse DNS records of a known attacker", () => {
    cy.unlistIp(V4);
    cy.postIpAsBot(V4).its("status").should("eq", 200);

    cy.visit(`/info?q=${V4}`);
    cy.get("main").should("contain.text", `${V4} found in following databases.`);

    cy.contains("h2", "ATKdb").should("be.visible");
    cy.contains("h2", "Reverse DNS").should("be.visible");
    // First seen / Last seen come from atkIps; the epoch is the stable part
    // because rewriteEpoch() replaces the visible text with a locale string.
    cy.contains("dt", "First seen")
      .parents("dl")
      .find("dd.unixepoch")
      .invoke("attr", "data-epoch")
      .should("match", /^\d+$/);
    cy.contains("dt", "Last seen")
      .parents("dl")
      .find("dd.unixepoch")
      .invoke("attr", "data-epoch")
      .should("match", /^\d+$/);
  });

  it("lists neighbouring IPs of the same /24 and links to them", () => {
    // The neighbours query is per /24, so the whole block must be under control.
    ["203.0.113.10", "203.0.113.11", "203.0.113.12"].forEach((ip) => cy.unlistIp(ip));
    cy.postIpAsBot(V4).its("status").should("eq", 200);
    cy.postIpAsBot("203.0.113.11").its("status").should("eq", 200);

    cy.visit(`/info?q=${V4}`);
    cy.contains("h2", "Subnets in ATK").parents("section").first().within(() => {
      cy.get("tbody tr").should("have.length", 2);
      cy.get('a[href="?q=203.0.113.10"]').should("have.text", "203.0.113.10");
      cy.get('a[href="?q=203.0.113.11"]').should("have.text", "203.0.113.11");
    });
  });

  it("treats a same-/24 neighbour as a hit even when the IP itself is unknown", () => {
    cy.unlistIp("203.0.113.11");
    cy.postIpAsBot("203.0.113.11").its("status").should("eq", 200);
    cy.unlistIp("203.0.113.12");

    visitInfo("203.0.113.12");
    cy.get("main").should("contain.text", "found in following databases.");
    cy.contains("h2", "ATKdb").should("not.exist");
    cy.contains("h2", "Subnets in ATK").should("be.visible");
  });

  // Some tests are migrated to Pest Browser Testing.
});
