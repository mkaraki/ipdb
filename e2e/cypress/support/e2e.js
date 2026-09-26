import "./commands";

// The app under :8080 does not serve its own /assets/*, which leaves every page
// throwing "rewriteEpoch is not defined". Answer those requests from the assets
// baked into config.env by cypress.config.js so the suite exercises the real
// client-side behaviour.
beforeEach(() => {
  const assets = Cypress.expose("assets") || {};
  cy.intercept("**/assets/**", (req) => {
    const asset = assets[req.url.replace(/^https?:\/\/[^/]+/, "")];
    req.reply(
      asset
        ? { statusCode: 200, body: asset.body, headers: { "Content-Type": asset.type } }
        : { statusCode: 404, body: "" }
    );
  });
});
