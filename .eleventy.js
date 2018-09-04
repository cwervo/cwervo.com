module.exports = function(eleventyConfig) {
    eleventyConfig.addPassthroughCopy("src/assets")

    let markdownIt = require("markdown-it");
    let markdownItFootnote = require("markdown-it-footnote");
    let options = {
        html: true
    };
    let markdownLib = markdownIt(options).use(markdownItFootnote);
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
