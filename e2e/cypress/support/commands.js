// Helpers for the ipdb app.
//
// Auth is HTTP Basic (see src/AuthMiddlewares.php). Browsers cannot answer a
// Basic challenge driven by cy.visit(), so for page loads we attach the header
// with cy.intercept instead. cy.request() takes an explicit `auth` option.

const basic = (user, pass) => `Basic ${btoa(`${user}:${pass}`)}`;

// Parses an HTML response body into a Document for cheap server-side queries.
const parseHtml = (body) => new DOMParser().parseFromString(body, "text/html");

// Attaches a Basic auth header to every request whose URL matches `matches`
// (a glob, regex or predicate, as accepted by cy.intercept).
Cypress.Commands.add("basicAuthOn", (matches, user, pass) =>
  cy.intercept(matches, (req) => {
    req.headers["Authorization"] = basic(user, pass);
  })
);

// Logs in as USER_ATK_MANAGER for everything under /atk/admin.
Cypress.Commands.add("asManager", () => {
  cy.basicAuthOn(
    /\/atk\/admin/,
    Cypress.expose("managerUser"),
    Cypress.expose("managerPass")
  );
});

// Logs in as USER_ATK_REPORTER for the bot post endpoints
// (/atk/post, /atk/post.php, /wwwroot/atk/post.php).
Cypress.Commands.add("asBot", () => {
  cy.basicAuthOn(
    /\/atk\/post/,
    Cypress.expose("botUser"),
    Cypress.expose("botPass")
  );
});

// Posts an IP to /atk/post as the reporter bot. Does not assert the status so
// tests can cover both the success and the validation-failure paths.
Cypress.Commands.add("postIpAsBot", (ip, extra = {}) =>
  cy.request({
    method: "POST",
    url: "/atk/post",
    auth: { user: Cypress.expose("botUser"), pass: Cypress.expose("botPass") },
    form: true,
    body: { ip, noredirect: "1", ...extra },
    failOnStatusCode: false,
  })
);

// Removes an IP from ATKdb as the manager, so a spec can assert against a
// known-empty starting state regardless of what earlier runs left behind.
Cypress.Commands.add("unlistIp", (ip) =>
  cy.request({
    method: "POST",
    url: "/atk/admin/unlist",
    auth: { user: Cypress.expose("managerUser"), pass: Cypress.expose("managerPass") },
    form: true,
    body: { ip },
    failOnStatusCode: false,
  })
);

// Empties a whole /24, because the /info neighbours query lists the entire block.
Cypress.Commands.add("unlistBlock", (prefix) =>
  cy.request("/atk/list").then((res) => {
    const ips = Array.from(parseHtml(res.body).querySelectorAll('a[href^="../info?q="]')).map(
      (a) => decodeURIComponent(a.getAttribute("href").split("q=")[1])
    );
    ips.filter((ip) => ip.startsWith(prefix)).forEach((ip) => cy.unlistIp(ip));
  })
);

// True when `ip` has a row in the public /atk/list table.
Cypress.Commands.add("ipInAtkList", (ip) =>
  cy.request("/atk/list").then((res) => {
    expect(res.status, "/atk/list status").to.eq(200);
    return parseHtml(res.body).querySelector(`a[href="../info?q=${ip}"]`) !== null;
  })
);

// The ignore-list row ids whose rendered "network/cidr" matches `network`.
Cypress.Commands.add("ignoreListIdsFor", (network) =>
  cy.request({
    url: "/atk/admin/ignorelist/",
    auth: { user: Cypress.expose("managerUser"), pass: Cypress.expose("managerPass") },
    failOnStatusCode: false,
  }).then((res) => {
    expect(res.status, "ignorelist status").to.eq(200);
    return Array.from(parseHtml(res.body).querySelectorAll("table tbody tr"))
      .filter((tr) => tr.cells[0].textContent.trim().startsWith(network))
      .map((tr) => tr.querySelector('input[name="id"]').value);
  })
);
