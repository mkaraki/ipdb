const { defineConfig } = require("cypress");
const fs = require("fs");
const path = require("path");

// The dev server on :8080 only runs the front controller, so the app's own
// /assets/* requests 404 and rewriteEpoch()/nanoToSeconds() stay undefined.
// The pages hard-code those absolute paths, so hand them over from the repo
// instead of editing the app. Delete the assets bit once :8080 serves them.
const ASSET_ROOT = path.resolve(__dirname, "..", "assets");
const ASSET_TYPES = { ".js": "text/javascript", ".css": "text/css" };

const readAssets = (dir, prefix = "") =>
  fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const rel = `${prefix}/${entry.name}`;
    const abs = path.join(dir, entry.name);
    return entry.isDirectory()
      ? readAssets(abs, rel)
      : [{ [rel]: { body: fs.readFileSync(abs, "utf8"), type: ASSET_TYPES[path.extname(abs)] || "text/plain" } }];
  });

module.exports = defineConfig({
  // Credentials must match USER_ATK_REPORTER / USER_ATK_MANAGER in
  // _config.dist.php. Both accounts use the password "password".
  expose: Object.assign(
    {
      managerUser: "admin",
      managerPass: "password",
      botUser: "example",
      botPass: "password",
    },
    { assets: Object.assign({}, ...readAssets(ASSET_ROOT, "/assets")) }
  ),
  e2e: {
    baseUrl: "http://127.0.0.1:8080",
    specPattern: "cypress/e2e/**/*.cy.js",
    defaultCommandTimeout: 15000,
    // Posting a new IP triggers a blocking gethostbyaddr() PTR lookup server side.
    requestTimeout: 60000,
    responseTimeout: 60000,
    pageLoadTimeout: 60000,
  },
});
