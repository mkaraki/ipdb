// Honeypot endpoints. They are public, always answer 500 "Error." and record
// the caller as a frontend attacker in atkIps.
const ENDPOINTS = [
  "/wp-admin",
  "/wp-admin/admin-ajax.php",
  "/xmlrpc.php",
  "/.env",
  "/.git/config",
  "/.git/HEAD",
];

describe("Honeypot endpoints", () => {
  it("always answers 500 Error. regardless of method", () => {
    ENDPOINTS.forEach((url) => {
      cy.request({ url, failOnStatusCode: false }).then((res) => {
        expect(res.status, `${url} status`).to.eq(500);
        expect(res.body, `${url} body`).to.eq("Error.");
      });
      cy.request({ url, method: "POST", failOnStatusCode: false }).then((res) => {
        expect(res.status, `POST ${url} status`).to.eq(500);
        expect(res.body, `POST ${url} body`).to.eq("Error.");
      });
    });
  });

  it("does not leak the matched path back to the caller", () => {
    ENDPOINTS.forEach((url) => {
      cy.request({ url, failOnStatusCode: false }).its("body").should("not.contain", "php");
    });
  });

  it("flags the calling IP as a frontend attacker", () => {
    // The app shows the IP it thinks we are, so reuse that rather than guessing.
    cy.request("/").then((res) => {
      const clientIp = Cypress.$(Cypress.$.parseHTML(res.body)).find("code").first().text().trim();
      expect(clientIp, "resolved client IP").to.not.be.empty;

      cy.request({ url: "/.env", failOnStatusCode: false }).its("status").should("eq", 500);

      cy.visit("/atk/list");
      const row = cy.get("table tbody tr").filter(`:has(a[href="../info?q=${clientIp}"])`);
      row.should("have.length", 1);
      // Column 4 is "Frontend"; only the trailing date columns use colspan.
      row.find("td").eq(3).should("have.text", "Yes");
    });
  });
});
