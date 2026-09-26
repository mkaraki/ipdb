// Everything under /atk/admin -- the manager UI, guarded by USER_ATK_MANAGER.
const AUTH = {
  user: Cypress.expose("managerUser"),
  pass: Cypress.expose("managerPass"),
};
const PAGES = [
  ["/atk/admin/", "ATK DB Admin"],
  ["/atk/admin/postform", "Post IP Info to ATK"],
  ["/atk/admin/batchpostform", "Batch Post IP Info to ATK"],
  ["/atk/admin/unlistform", "Unlist IP from ATKdb"],
  ["/atk/admin/ignorelist/", "Ignore List of ATKdb"],
];
const V4 = "198.51.100.40";

const post = (url, body, options = {}) =>
  cy.request({ method: "POST", url, form: true, body, auth: AUTH, failOnStatusCode: false, ...options });

describe("ATK admin auth", () => {
  it("challenges anonymous callers on every admin page", () => {
    PAGES.forEach(([url]) => {
      cy.request({ url, failOnStatusCode: false }).then((res) => {
        expect(res.status, `${url} status`).to.eq(401);
        expect(res.body).to.eq("Unauthorized");
        expect(res.headers["www-authenticate"]).to.contain('Basic realm="ipdb"');
      });
    });
  });

  it("challenges the reporter credentials too", () => {
    PAGES.forEach(([url]) => {
      cy.request({
        url,
        auth: { user: Cypress.expose("botUser"), pass: Cypress.expose("botPass") },
        failOnStatusCode: false,
      }).its("status")
        .should("eq", 401);
    });
  });

  it("serves every admin page to the manager", () => {
    cy.asManager();
    PAGES.forEach(([url, heading]) => {
      cy.visit(url);
      cy.get("h1").should("have.text", heading);
    });
  });

  it("links from the admin index to every form", () => {
    cy.asManager();
    cy.visit("/atk/admin/");
    cy.get("main li a").should("have.length", 4);
    [
      ["Manual Post", "postform"],
      ["Batch Post Form", "batchpostform"],
      ["Unlist IP", "unlistform"],
      ["Ignore List", "ignorelist/"],
    ].forEach(([label, href]) => {
      cy.contains("a", label).should("have.attr", "href", href).click();
      // The form pages carry an h1 but no nav back to the index.
      cy.get("h1").should("be.visible");
      cy.go("back");
    });
  });
});

describe("ATK admin manual post", () => {
  beforeEach(() => {
    cy.unlistIp(V4);
    cy.asManager();
    cy.visit("/atk/admin/postform");
  });

  it("renders the form with an empty log time defaulting to NA", () => {
    cy.get("#ip").should("have.attr", "name", "ip").and("be.empty");
    cy.get("#loggedat").should("have.attr", "name", "loggedat").and("have.value", "");
    cy.get("#loggedat-preview").should("have.text", "NA");
  });

  it("previews the chosen log time in the browser locale", () => {
    cy.get("#loggedat").type("1600000000");
    cy.get("#loggedat-preview").should("not.have.text", "NA");
  });

  it("converts a FortiGate nanosecond timestamp", () => {
    cy.get("#tsc-lg-fg").type("1600000000123456789");
    cy.contains("button", "Convert").click();
    cy.get("#loggedat").should("have.value", "1600000000");
    cy.get("#loggedat-preview").should("not.have.text", "NA");
  });

  it("blocks submission while the required IP field is empty", () => {
    cy.get('form[action="post"] input[type="submit"]').click();
    cy.location("pathname").should("include", "/atk/admin/postform");
    cy.get("#ip:invalid").should("exist");
  });

  it("posts an IP and redirects to the list", () => {
    cy.get("#ip").type(V4);
    cy.get('form[action="post"] input[type="submit"]').click();
    cy.location("pathname").should("eq", "/atk/list");
    cy.get('table a[href="../info?q=' + V4 + '"]').should("exist");
  });

  it("returns 200 instead of a redirect when noredirect is set", () => {
    post("/atk/admin/post", { ip: V4, noredirect: "1" }).its("status").should("eq", 200);
    post("/atk/admin/post", { ip: V4, no_redirect: "1" }).its("status").should("eq", 200);
    cy.ipInAtkList(V4).should("eq", true);
  });

  it("rejects an invalid IP and a non-numeric log time", () => {
    post("/atk/admin/post", { ip: "notanip" }).then((res) => {
      expect(res.status).to.eq(400);
      expect(res.body).to.eq("Invalid IP address.");
    });
    post("/atk/admin/post", { ip: V4, loggedat: "notanumber" }).then((res) => {
      expect(res.status).to.eq(400);
      expect(res.body).to.eq("Logged at value must be numeric.");
    });
    cy.ipInAtkList(V4).should("eq", false);
  });
});

describe("ATK admin batch post", () => {
  const SRC = "198.51.100.31";
  const logLine = (src, time) =>
    `date=2020-09-13 time=12:26:40 devname=wan1 logid=0100001000 type="utm" subtype="ips" ` +
    `eventtype="signature" eventtime=${time} srcip=${src} srcport=54321 dstip=192.0.2.55 ` +
    `dstport=443 attack="Sample.IPS.Signature"`;

  const parse = (text) => {
    cy.get("#fgt-ips-log").invoke("val", text);
    cy.contains("button", "Parse").click();
  };

  beforeEach(() => {
    cy.unlistIp(SRC);
    cy.asManager();
    cy.visit("/atk/admin/batchpostform");
  });

  it("starts with an empty preview table", () => {
    cy.get("#fgt-ips-log").should("be.empty");
    cy.get("#batch-preview tr").should("have.length", 0);
    cy.get("table thead th").should(
      "have.text",
      ["Time", "[Src]:Port", "[Dst]:Port", "Msg", "Action"].join("")
    );
  });

  it("parses a FortiGate signature log line into a preview row", () => {
    parse(logLine(SRC, "1600000000123456789"));
    cy.get("#batch-preview tr").should("have.length", 1);
    cy.get("#batch-preview tr").within(() => {
      cy.get("td").eq(0).should("have.attr", "data-epoch", "1600000000");
      cy.get("td").eq(1).should("have.text", `[${SRC}]:54321`);
      cy.get("td").eq(2).should("have.text", "[192.0.2.55]:443");
      cy.get("td").eq(3).should("have.text", "FortiGate IPS Signature: Sample.IPS.Signature");
      cy.contains("button", "Delete").should("exist");
    });
  });

  it("ignores lines that are not IPS signature events", () => {
    parse(`date=2020-09-13 devname=wan1 srcip=${SRC} type="traffic" subtype="forward"`);
    cy.get("#batch-preview tr").should("have.length", 0);
  });

  it("removes a preview row with the Delete button", () => {
    parse(logLine(SRC, "1600000000123456789"));
    cy.get("#batch-preview tr").should("have.length", 1);
    cy.contains("button", "Delete").click();
    cy.get("#batch-preview tr").should("have.length", 0);
    cy.ipInAtkList(SRC).should("eq", false);
  });

  it("posts the previewed rows and drains the table", () => {
    parse(logLine(SRC, "1600000000123456789"));
    cy.contains("button", "Post").click();
    cy.get("#batch-preview tr", { timeout: 30000 }).should("have.length", 0);
    cy.contains("button", "Post").should("be.enabled");
    cy.ipInAtkList(SRC).should("eq", true);
  });

  it("reports only once per distinct source IP", () => {
    parse(
      [
        logLine(SRC, "1600000000123456789"),
        logLine(SRC, "1600000001123456789"),
        logLine("198.51.100.32", "1600000002123456789"),
      ].join("\n")
    );
    cy.get("#batch-preview tr").should("have.length", 3);

    cy.contains("button", "Post").click();
    cy.get("#batch-preview tr", { timeout: 30000 }).should("have.length", 0);

    cy.visit("/atk/list");
    cy.get("table tbody tr")
      .filter(`:has(a[href="../info?q=${SRC}"])`)
      .find("td")
      .eq(4)
      .should("have.text", "1");
    cy.get('table a[href="../info?q=198.51.100.32"]').should("exist");
  });
});

describe("ATK admin unlist", () => {
  const IP = "198.51.100.50";

  beforeEach(() => {
    cy.asManager();
    cy.visit("/atk/admin/unlistform");
  });

  it("requires an IP address", () => {
    cy.get('form[action="unlist"] input[type="submit"]').click();
    cy.location("pathname").should("include", "/atk/admin/unlistform");
    cy.get("#ip:invalid").should("exist");
  });

  it("rejects an invalid IP", () => {
    post("/atk/admin/unlist", { ip: "notanip" }).then((res) => {
      expect(res.status).to.eq(400);
      expect(res.body).to.eq("Invalid IP address.");
    });
  });

  it("removes the IP from the list and from the info page", () => {
    cy.unlistIp(IP);
    post("/atk/admin/post", { ip: IP, noredirect: "1" }).its("status").should("eq", 200);
    cy.ipInAtkList(IP).should("eq", true);

    cy.get("#ip").type(IP);
    cy.get('form[action="unlist"] input[type="submit"]').click();
    cy.location("pathname").should("eq", "/atk/list");
    cy.get(`table a[href="../info?q=${IP}"]`).should("not.exist");

    cy.visit(`/info?q=${IP}`);
    cy.contains("h2", "ATKdb").should("not.exist");
  });

  it("succeeds silently for an IP that was never listed", () => {
    post("/atk/admin/unlist", { ip: "198.51.100.251" }, { followRedirect: false })
      .its("status")
      .should("eq", 303);
  });
});
