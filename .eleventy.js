const syntaxHighlight = require("@11ty/eleventy-plugin-syntaxhighlight");

module.exports = function(eleventyConfig) {
    eleventyConfig.addPassthroughCopy("src/assets")

    let markdownIt = require("markdown-it");
    // let markdownIt = require("markdown-it")({
    // });

    eleventyConfig.addPlugin(syntaxHighlight.configFunction);

    let markdownItFootnote = require("markdown-it-footnote");
    let markdownItAnchor = require("markdown-it-anchor");
    let markdownItAnchorOptions = {
        permalink: true,
    };

    let markdownItOptions = {
        html: true,
        ...markdownItAnchorOptions,
    };

    let markdownLib = markdownIt(markdownItOptions).use(markdownItFootnote).use(markdownItAnchor);
    eleventyConfig.setLibrary("md", markdownLib);

    return {
        templateFormats: [
            "md",
            "pug",
            "html",
            "css" // css is not yet a valid template extension
        ],
        dir: {
            input: "src",
            output: "docs"
        },
        passthroughFileCopy: true
    };
};
