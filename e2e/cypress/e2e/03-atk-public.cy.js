// GET /atk/, GET /atk/list and GET /atk/fgfeed -- the public reporting pages.
const V4 = "198.51.100.7";
const V6 = "2001:db8:1::7";
const feed = (params) => `/atk/fgfeed${params ? `?${params}` : ""}`;

describe("ATK dashboard", () => {
  beforeEach(() => cy.visit("/atk/"));

  it("summarises the list size and links to the full list", () => {
    cy.title().should("eq", "ATK DB - IPDB");
    cy.get("h1").should("have.text", "ATK DB");
    // The count lives in the header block, outside <main>.
    cy.contains("p", "IPs in list").invoke("text").should("match", /^\d+ IPs in list\./);
    cy.contains("a", "See full list").should("have.attr", "href", "list").click();
    cy.url().should("include", "/atk/list");
  });

  it("shows a row per lookback window", () => {
    cy.contains("h2", "Stats").should("be.visible");
    const windows = ["1 day", "7 days", "14 days", "30 days", "60 days", "180 days", "365 days"];
    windows.forEach((w) => {
      cy.contains("td", `Last ${w}`)
        .should("be.visible")
        .next()
        .invoke("text")
        .then((t) => t.trim())
        .should("match", /^\d+$/);
    });
  });

  it("lists the top countries and top ASNs", () => {
    // Each heading sits in its own <section> with exactly one table.
    cy.contains("h3", "Top Countries (Last 30 days)").should("be.visible");
    cy.contains("h3", "Top ASNs (Last 30 days)").should("be.visible");
    // GeoIP data is optional, so only the shape of these tables is guaranteed.
    cy.contains("h3", "Top Countries (Last 30 days)")
      .next()
      .find("th")
      .should("have.text", ["Country", "Count"].join(""));
    cy.contains("h3", "Top ASNs (Last 30 days)")
      .next()
      .find("th")
      .should("have.text", ["ASN", "Count"].join(""));
  });
});

describe("ATK full list", () => {
  beforeEach(() => cy.visit("/atk/list"));

  it("renders the seven column headers", () => {
    cy.title().should("eq", "ATK IP List - IPDB");
    cy.get("h1").should("have.text", "ATK IP List");
    cy.get("table thead th").should(
      "have.text",
      ["IP (FQDN)", "Country", "ASN", "Frontend", "Count", "First Seen", "Last Seen"].join("")
    );
  });

  it("shows the pagination summary and at most 100 rows", () => {
    cy.get("main p")
      .invoke("text")
      .then((t) => t.replace(/\s+/g, " ").trim())
      .should("match", /^Page \d+ of \d+ \(\d+ IPs total\)$/);
    cy.get("table tbody tr").should("have.length.at.most", 100);
  });

  it("drills down from a row into the info page", () => {
    cy.unlistIp("198.51.100.9");
    cy.postIpAsBot("198.51.100.9").its("status").should("eq", 200);
    cy.visit("/atk/list");
    cy.get('table a[href="../info?q=198.51.100.9"]').click();
    cy.url().should("include", "/info?q=198.51.100.9");
    cy.get("h1").should("have.text", "IPdb Search");
  });

  it("clamps out-of-range and non-numeric page numbers to page 1", () => {
    cy.visit("/atk/list?page=abc");
    cy.get("main p").should("contain.text", "Page 1 of");
    cy.visit("/atk/list?page=0");
    cy.get("main p").should("contain.text", "Page 1 of");
    cy.visit("/atk/list?page=-5");
    cy.get("main p").should("contain.text", "Page 1 of");
  });
});

describe("ATK feed", () => {
  beforeEach(() => {
    cy.unlistIp(V4);
    cy.unlistIp(V6);
    cy.postIpAsBot(V4).its("status").should("eq", 200);
    cy.postIpAsBot(V6).its("status").should("eq", 200);
  });

  const body = (url) => cy.request(url).then((r) => r.body);
  const lines = (url) => body(url).then((b) => b.split("\n").slice(1).filter(Boolean));

  it("serves plain text starting with the banner comment", () => {
    cy.request(feed()).then((res) => {
      expect(res.status).to.eq(200);
      expect(res.headers["content-type"]).to.contain("text/plain");
      expect(res.body.split("\n")[0]).to.eq("# Attack detected IP feed");
    });
  });

  it("is also reachable with the legacy .php suffix", () => {
    cy.request("/atk/fgfeed.php").then((res) => {
      expect(res.status).to.eq(200);
      expect(res.body.split("\n")[0]).to.eq("# Attack detected IP feed");
    });
  });

  it("emits individual hosts by default", () => {
    // Host mode appends /32 to IPv4 but leaves IPv6 bare.
    lines(feed()).should("include.members", [`${V4}/32`, V6]);
  });

  it("collapses to /24 and /64 prefixes with range=net", () => {
    lines(feed("range=net")).should("include.members", ["198.51.100.0/24", "2001:db8:1::/64"]);
  });

  it("filters by address family", () => {
    lines(feed("family=ipv4")).should("include.members", [`${V4}/32`]);
    lines(feed("family=ipv4")).should("not.include", V6);
    lines(feed("family=ipv6")).should("include.members", [V6]);
    lines(feed("family=ipv6")).should("not.include", `${V4}/32`);
  });

  it("returns an empty feed for an unknown family", () => {
    lines(feed("family=bogus")).should("have.length", 0);
  });

  it("refuses a since value in the future", () => {
    cy.request({ url: feed("since=-86400"), failOnStatusCode: false }).then((res) => {
      expect(res.status).to.eq(400);
      expect(res.body).to.eq("You can not specify now or future date");
    });
  });
});
