// .eleventy.js
module.exports = function(eleventyConfig) {
    eleventyConfig.addPassthroughCopy("assets");
    eleventyConfig.addPassthroughCopy("img");

    return {
        passthroughFileCopy: true
    };
};
