import assert from "node:assert/strict";
import test from "node:test";

async function render(path = "/") {
  const workerUrl = new URL("../dist/server/index.js", import.meta.url);
  workerUrl.searchParams.set("test", `${process.pid}-${Date.now()}-${path}`);
  const { default: worker } = await import(workerUrl.href);
  return worker.fetch(new Request(`http://localhost${path}`, { headers: { accept: "text/html" } }), { ASSETS: { fetch: async () => new Response("Not found", { status: 404 }) } }, { waitUntil() {}, passThroughOnException() {} });
}

test("server-renders the OneLegalPro launch page and approved boundaries", async () => {
  const response = await render();
  assert.equal(response.status, 200);
  assert.match(response.headers.get("content-type") ?? "", /^text\/html\b/i);
  assert.match(response.headers.get("content-security-policy") ?? "", /frame-ancestors 'none'/);
  assert.equal(response.headers.get("permissions-policy"), "camera=(), geolocation=(), microphone=(), payment=(), usb=()");
  assert.equal(response.headers.get("referrer-policy"), "strict-origin-when-cross-origin");
  assert.equal(response.headers.get("strict-transport-security"), "max-age=31536000; includeSubDomains");
  assert.equal(response.headers.get("x-content-type-options"), "nosniff");
  assert.equal(response.headers.get("x-frame-options"), "DENY");
  const html = await response.text();
  assert.match(html, /<title>OneLegalPro — Matter Desk for Thai law firms<\/title>/i);
  assert.match(html, /A calmer way to run the matters that matter\./);
  assert.match(html, /Founding-firm pilot/);
  assert.match(html, /No automated conflict checking/);
  assert.match(html, /No Ethical Walls/);
  assert.match(html, /No automated reminders or notifications/);
  assert.match(html, /No document or attachment storage/);
  assert.match(html, /does not store the form contents/);
  assert.match(html, /Jand Corp Company Limited/);
  assert.match(html, /https:\/\/onelegalpro\.com\/og\.png/);
  assert.doesNotMatch(html, /codex-preview|react-loading-skeleton|Your site is taking shape/i);
});

test("server-renders distinct privacy and terms pages", async () => {
  const [privacy, terms] = await Promise.all([render("/privacy"), render("/terms")]);
  assert.equal(privacy.status, 200);
  assert.equal(terms.status, 200);
  const privacyHtml = await privacy.text();
  const termsHtml = await terms.text();
  assert.match(privacyHtml, /<title>Privacy Notice — OneLegalPro<\/title>/i);
  assert.match(privacyHtml, /The website does not persist the form contents/);
  assert.match(privacyHtml, /24 months after the last substantive contact/);
  assert.match(privacyHtml, /does not enrol you in marketing or a mailing list/);
  assert.doesNotMatch(privacyHtml, /Draft for owner legal review|must be completed and approved before publication/);
  assert.match(termsHtml, /<title>Website Terms — OneLegalPro<\/title>/i);
  assert.match(termsHtml, /does not create a lawyer-client/);
  assert.match(termsHtml, /governed by Thai law/);
  assert.match(termsHtml, /English is the controlling language/);
  assert.doesNotMatch(termsHtml, /Draft for owner legal review|intentionally left for the owner/);
  assert.doesNotMatch(privacyHtml, /https:\/\/onelegalpro\.com\/og\.png/);
  assert.doesNotMatch(termsHtml, /https:\/\/onelegalpro\.com\/og\.png/);
});
