// POST /atk/post, /atk/post.php and /wwwroot/atk/post.php
// -- the reporter bot API, guarded by USER_ATK_REPORTER.
const V4 = "198.51.100.20";
const POST_URLS = ["/atk/post", "/atk/post.php", "/wwwroot/atk/post.php"];
const AUTH = {
  user: Cypress.expose("botUser"),
  pass: Cypress.expose("botPass"),
};

const post = (url, body, auth) =>
  cy.request({
    method: "POST",
    url,
    form: true,
    body,
    auth,
    failOnStatusCode: false,
  });

describe("ATK bot API auth", () => {
  it("challenges anonymous callers on every post endpoint", () => {
    POST_URLS.forEach((url) => {
      post(url, { ip: V4 }).then((res) => {
        expect(res.status, `${url} status`).to.eq(401);
        expect(res.body).to.eq("Unauthorized");
        expect(res.headers["www-authenticate"]).to.contain('Basic realm="ipdb"');
      });
    });
  });

  it("rejects a wrong password and an unknown user", () => {
    post("/atk/post", { ip: V4 }, { user: Cypress.expose("botUser"), pass: "wrong" })
      .its("status")
      .should("eq", 401);
    post("/atk/post", { ip: V4 }, { user: "nobody", pass: Cypress.expose("botPass") })
      .its("status")
      .should("eq", 401);
  });

  it("does not let reporter credentials reach the manager area", () => {
    cy.request({
      url: "/atk/admin/",
      auth: AUTH,
      failOnStatusCode: false,
    }).then((res) => {
      expect(res.status).to.eq(401);
      expect(res.body).to.eq("Unauthorized");
    });
  });
});

describe("ATK bot API reporting", () => {
  beforeEach(() => cy.unlistIp(V4));

  it("accepts an IP on all three compatible endpoints", () => {
    POST_URLS.forEach((url, i) => {
      post(url, { ip: `198.51.100.3${i}` }, AUTH).its("status").should("eq", 200);
    });
    POST_URLS.forEach((_, i) => {
      cy.ipInAtkList(`198.51.100.3${i}`).should("eq", true);
    });
  });

  it("rejects malformed and non-public addresses", () => {
    ["notanip", "", "10.0.0.1", "192.168.1.1", "fd00::1"].forEach((ip) => {
      post("/atk/post", { ip }, AUTH).then((res) => {
        expect(res.status, `ip=${JSON.stringify(ip)}`).to.eq(400);
        expect(res.body).to.eq("Invalid IP address.");
      });
    });
  });

  it("rejects a non-numeric loggedat but defaults a missing one to now", () => {
    post("/atk/post", { ip: V4, loggedat: "notanumber" }, AUTH).then((res) => {
      expect(res.status).to.eq(400);
      expect(res.body).to.eq("Logged at value must be numeric.");
    });
    cy.ipInAtkList(V4).should("eq", false);

    post("/atk/post", { ip: V4 }, AUTH).its("status").should("eq", 200);
    cy.visit("/atk/list");
    cy.get("table tbody tr")
      .filter(`:has(a[href="../info?q=${V4}"])`)
      .find("td.unixepoch")
      .invoke("attr", "data-epoch")
      .then(Number)
      .should("be.closeTo", Date.now() / 1000, 300);
  });

  it("increments the attack counter on repeated reports", () => {
    post("/atk/post", { ip: V4 }, AUTH).its("status").should("eq", 200);
    post("/atk/post", { ip: V4 }, AUTH).its("status").should("eq", 200);
    post("/atk/post", { ip: V4 }, AUTH).its("status").should("eq", 200);

    cy.visit("/atk/list");
    cy.get("table tbody tr")
      .filter(`:has(a[href="../info?q=${V4}"])`)
      .find("td")
      .eq(4)
      .should("have.text", "3");
  });

  it("honours an explicit loggedat timestamp", () => {
    const stamp = 1600000000;
    post("/atk/post", { ip: V4, loggedat: String(stamp) }, AUTH).its("status").should("eq", 200);

    cy.visit("/atk/list");
    cy.get("table tbody tr")
      .filter(`:has(a[href="../info?q=${V4}"])`)
      .find("td.unixepoch")
      .invoke("attr", "data-epoch")
      .should("eq", String(stamp));
  });

  it("publishes the reported IP to the info page and the feed", () => {
    post("/atk/post", { ip: V4 }, AUTH).its("status").should("eq", 200);

    cy.visit(`/info?q=${V4}`);
    cy.get("main").should("contain.text", `${V4} found in following databases.`);

    cy.request("/atk/fgfeed")
      .its("body")
      .should("contain", `${V4}/32`);
  });
});
