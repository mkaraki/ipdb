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

  it("exposes the search box with its placeholder and submit label", () => {
    cy.get("#search-ip-box")
      .should("have.attr", "name", "q")
      .and("have.attr", "placeholder", "IP Address");
    cy.get('form[action="info"]').find('input[type="submit"]').should("have.value", "Search");
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

  it("searches for an IP through the form", () => {
    cy.get("#search-ip-box").type("203.0.113.10{enter}");
    cy.location("search").should("eq", "?q=203.0.113.10");
    cy.title().should("eq", "203.0.113.10 - IPDB");
    cy.get("h1").should("have.text", "IPdb Search");
  });
});
