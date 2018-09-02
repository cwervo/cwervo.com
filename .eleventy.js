module.exports = function(eleventyConfig) {
    eleventyConfig.addPassthroughCopy("src/assets")
    // You can return your Config object (optional).
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
