const Eleventy = require("@11ty/eleventy");

(async function() {
	let elev = new Eleventy();
	await elev.write();
})();
