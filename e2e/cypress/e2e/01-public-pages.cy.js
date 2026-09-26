// GET / and the public layout that every other page inherits.
describe("Public pages", () => {
  beforeEach(() => cy.visit("/"));

  it("renders the home page shell", () => {
    cy.title().should("eq", "Home - IPDB");
    cy.get("h1").should("have.text", "IPdb");
    cy.contains("h2", "Search").should("be.visible");
    cy.contains("h2", "Services").should("be.visible");
    cy.contains("You are accessing from")
      .should("be.visible")
      .find("code")
      .should("not.be.empty");
  });

  it("renders the shared layout chrome", () => {
    cy.get('link[href="/assets/styles/main.css"]').should("exist");
    cy.get('link[href="/assets/styles/table.css"]').should("exist");
    cy.get('meta[name="robots"]').should("not.exist");
    cy.get("footer").should("contain.text", "GeoLite2 data created by MaxMind");
    cy.get('footer a[href="https://www.maxmind.com"]').should("exist");
  });

  it("navigates to the attacker list and the full list from the home page", () => {
    cy.contains("a", "Attacker List").should("have.attr", "href", "atk/").click();
    cy.url().should("include", "/atk/");
    cy.title().should("eq", "ATK DB - IPDB");

    cy.visit("/");
    cy.contains("a", "Full list").should("have.attr", "href", "atk/list").click();
    cy.url().should("include", "/atk/list");
    cy.title().should("eq", "ATK IP List - IPDB");
  });

  // Some tests are migrated to Pest Browser Testing.

});
