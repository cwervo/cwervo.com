---
title: Everything I Know About Launching iOS AR Quick Look From The Web
date: "2019-10-02"
includeModelViewer0dot6: true
syntaxHighlighting: true
---

_TL;DR_: There's an amazing feature called AR Quick Look on iOS that allows you
to preview 3D models. There's a bunch of info on how to do this from inside
native apps but not a lot of specifics (outside of WWDC talks) on how to use
this feature from inside HTML/JavaScript. This page is an attempt to remedy that!
There's a lot of info here, so here's a table of contents to get you started:

---

[[toc]]

---

## 👋🏼

Did you know you can launch 3D models from a web page? There's a helpful web component called <a href="https://github.com/GoogleWebComponents/model-viewer"><code>&lt;model-viewer&gt;</code></a> that will handle this for you. It'll look something like this:

<model-viewer ar src="/assets/3D-models/logo.glb" ios-src="/assets/3D-models/logo-3m-scaled.usdz" auto-rotate camera-controls background-color="#2EAFAC" alt="Spinning AC logo" quick-look-browsers="safari chrome"></model-viewer>
<div class="caption">A 3D version of my personal logo displayed using <code>&lt;model-viewer&gt;</code>
</div>

... & the source code looks like this:

```html
<model-viewer
    src="/assets/3D-models/logo.glb"
    ios-src="/assets/3D-models/logo-3m-scaled.usdz"
    ar auto-rotate camera-controls background-color="#2EAFAC">
</model-viewer>
```

On Android devices this enables an AR feature called Scene Viewer:

![A screen capture of what this example looks like in Android Scene Viewer](/assets/arquicklook-blog-post/3D-logo-scene-viewer.jpg)

<div class="caption credit">(Thanks to Jordan Santell for this screencap of Scene Viewer on an Android device!)</div>

& on iOS it launches a feature called AR Quick Look:

![AR Quick Look screenshot, previewing 3D personal logo](/assets/arquicklook-blog-post/logo-quicklock-screencap.jpg)


Okay, that's cool! How does it work? Well under the hood this web component — [`<model-viewer>`](https://github.com/GoogleWebComponents/model-viewer) — is using browser-specific attributes to launch proprietary AR features (translation: this will only work in specific browsers & is not standard HTML!) On iOS devices it's using  [iOS Quick Look](https://developer.apple.com/augmented-reality/quick-look/) & on Android it's using [Android's AR Scene Viewer](https://developers.google.com/ar/develop/java/scene-viewer). While Google documents exactly how Scene Viewer should be launched from HTML, Apple's HTML Quick Look documentation is super sparse.

## Apple's Documentation on launching Quick Look from the Web

It's not easily searchable on [their documentation website](https://developer.apple.com/documentation/) & all I could find officially documenting it is:

- Three minutes (16:30 — 19:50) in a WWDC talk from 2018 — [Integrating Apps and Content with AR Quick Look](https://developer.apple.com/videos/play/wwdc2018/603/)
    - [Four slides in the accompanying PDF deck](https://devstreaming-cdn.apple.com/videos/wwdc/2018/603augiuv41xoowslk8/603/603_integrating_apps_and_content_with_ar_quick_look.pdf) (21 through 25, if you're curious)
- A few minutes in a follow up WWDC talk from 2019 — [Advances in AR Quick Look](https://developer.apple.com/videos/play/wwdc2019/612)
    - In [the accompanying PDF deck](https://devstreaming-cdn.apple.com/videos/wwdc/2019/612umedd7bboc1/612/612_advances_in_ar_quick_look.pdf) we have 38 slides (126 - 164).
- [This section in](https://developer.apple.com/documentation/arkit/previewing_a_model_with_ar_quick_look#3263412) an article on 'Previewing a Model with AR Quick Look'
- [This post](https://webkit.org/blog/8421/viewing-augmented-reality-assets-in-safari-for-ios/) from the WebKit engineering blog from 2018

... and that's it! There's a [wealth](https://developer.apple.com/design/human-interface-guidelines/ios/system-capabilities/quick-look/). [of](https://developer.apple.com/library/archive/documentation/FileManagement/Conceptual/DocumentInteraction_TopicsForIOS/Introduction/Introduction.html). [information](https://developer.apple.com/documentation/quicklook). on the app-based way to launch Quick Look, but surprisingly little on how to launch it from the web. This post exists because I've learned a bunch about launching iOS Quick Look from the browser while using it <a href="http://movableink.com/" target="blank_">at work</a> & I want to save others time!


## Everything I know

### The Image Tag Is Required

One thing they don't note in PDF's is that the image tag is **required**. You can't just make a link with `href="eg.usdz"` & `rel=ar` — it needs to have a direct child that's an image (e.g. an `img` or `picture` tag). That is, in HTML, you *need* this structure:

```html
<a href="example.usdz" rel="ar">
    <img href="eg.jpg>" />
</a>
<!-- or -->
<a rel="ar" href="example.usdz">
    <picture>
        <source srcset="wide-eg.png"
                media="(min-width: 600px)">
        <img src="eg.png">
    </picture>
</a>
```

### The Image Tag Must Be The First Child

I ran a [little test](https://test-ios-quicklook-js.glitch.me#testing-directness-of-image) & as of 2019-10-02, on iOS 13, it appears that it's a strict requirement for the `img` tag to be the _first_ nested child of the `rel=ar` link element. If it's the second element, or even nested in the first child element, Safari fails to recognize it as an AR Quick Look element & instead will link to the USDZ directly. You can see this because the special Quick Look box icon doesn't appear in the top right of any elements after the first:

![AR Quick Look Preview](/assets/arquicklook-blog-post/directness-test-screenshot.jpg)

<div class="caption">A screenshot from <a href="https://test-ios-quicklook-js.glitch.me/">test-ios-quicklook-js.glitch.me</a> showing that from HTML the only way to activate AR Quick Look on iOS is to have the direct, first child of a <code>rel=ar</code> element be an <code>img</code></div>

What if you don't want to use a preview image? You can actually get away with something like this:

```html
<a href="example.usdz" rel="ar">
    <img /> <!-- remember, this has to be first! -->
    <!-- Put whatever else you want in here -->
</a>
```

If you're on iOS 13 right now try <a href="/assets/3D-models/logo-3m-scaled.usdz" rel="ar"><img style="display: inline;"><span>hitting this piece of text</span> </a> & you should see AR Quick Look pop up with the model from the beginning of the post! That link actually has an `img` with no `src` embedded into it, following the markup above.

### You can link to Data URI's & blobs as well

As of 2019 you can now use [data URI's](https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs) or [blobs](https://developer.mozilla.org/en-US/docs/Web/API/Blob) instead of linking to the `.usdz` directly.

```html
<a rel=ar href="data:model/vnd.usdz+zip;base64, <base64 encoded string>" >
   download="example.usdz">
    <img src="eg.jpg">
</a>
<!-- or, Reality file: -->
<a rel="ar" href="data:model/vnd.usdz+zip;base64,<base64 encoded string>"
   download=“example.usdz”>
    <img src=“eg.jpg”>
</a>

<a rel="ar" href="blob:<generated URL string>" download=“asset.usdz”>
 <img src=“asset-thumbnail.jpg”>
</a>

```

### Controlling scale of the models

As of Safari 12.2 you can force true scale of models by _disabling scaling_:

```html
<!--
For allowsContentScaling:
0 = off
1 = on
-->

https://developer.apple.com/arkit/gallery/toy_biplane.usdz#allowsContentScaling=0
```

If you do this you'll get a Quick Look scene where the object immediately
bounces back to `100%` to show that it cannot be re-sized:

<video playsinline autoplay=true muted=true loop=true  width="50%" src="/assets/arquicklook-blog-post/allowsContentScaling-demo_handbrake.mp4"></video>

<div class="notice">

While you can <a
href="./#launching-without-a-preview-image-using-javascript">launch Quick Look
from a browser that's not Safari</a>, as of 2019-10-02 this won't be respected
in other browsers.

</div>

### Setting the Share Link

In Quick Look you get a 'Share' icon:

![Image showing what the share icon in Quick Look looks like](/assets/arquicklook-blog-post/highlight-share-sheet-icon.jpg)

By default this will share a link to the model itself:

```html
<a href="http://cwervo.com/assets/3D-models/logo-3m-scaled.usdz" rel="ar">
```

![Image showing what the default share sheet looks like](/assets/arquicklook-blog-post/default-share.jpg)


But if you add `#canonicalWebPageURL=http://example.com` to the end of that you
can send people to a more useful URL. In the example below I've set `#canonicalWebPageURL=http://cwervo.com`:

![Image highlighting how with the canonicalWebPageURL](/assets/arquicklook-blog-post/custom-share-sheet.jpg)

If you'd like to set both the scaling option & the canonical share URL you can use:

```html
#allowsContentScaling=0&canonicalWebPageURL=https://example.com
```

<div class="notice">

As with <code>allowsContentScaling</code>, the <code>canonicalWebPageURL</code>
fragment only seems to work in Safari as of 2019-10-02.

</div>

## You need to have the right MIME type

Before iOS 12.2 the MIME type for USDZ's was ~~`model/vnd.pixar.usd`~~ or
~~`model/usd`~~. These are no longer the official MIME types — as of 2019 you
want to use `model/vnd.usdz+zip` for USDZ's & `model/vnd.reality`

```bash
# If you're using Apache, this is how you'd add the MIME types for:

# USDZ files for iOS AR Quicklook:
AddType model/vnd.usdz+zip .usdz

# Reality files
AddType model/vnd.reality .reality
```

## iOS AR Quick Look & JavaScript

### Detecting Web AR Quick Look Support

The **Feature Detection** section in Webkit's ["Viewing Augmented Reality Assets in Safari for iOS"](https://webkit.org/blog/8421/viewing-augmented-reality-assets-in-safari-for-ios/) post shows us how to detect if `ar` is a valid attribute in the browser we're in:

```javascript
const a = document.createElement("a");
if (a.relList.supports("ar")) {
  // AR is available.
}
```

<div class="notice">

If you want to test if your device can run iOS' AR Quick Look feature I <a href="https://test-ios-quicklook-js.glitch.me/quicklook-available.html">made a website that will tell you!</a> All it does is run the above snippet of Javascript & then print Yes/No.

</div>

### Launching without a preview image using JavaScript

On my [Test iOS Quick Look page](https://glitch.com/~test-ios-quicklook-js) I have a function called `launchIOSQuick LookAR` that's the minimum JavaScript you need to launch iOS AR Quick Look

```javascript
// `usdzSrc` is expected to be a string path to a `.usdz` file
function launchIOSQuick LookAR(usdzSrc) {
  const anchor = document.createElement('a');
  anchor.setAttribute('rel', 'ar');
  anchor.appendChild(document.createElement('img'));
  anchor.setAttribute('href', usdzSrc);
  anchor.click();
}
```
<div class="caption">Note: this code is basically a modified version of the <a href="https://github.com/GoogleWebComponents/model-viewer/blob/master/src/features/ar.ts#L27-L36"> <code>openIOSARQuick Look</code></a> in <code>&lt;model-viewer&gt;</code></div>

Yep! There's no API for it — all we can do for now is create offscreen HTML elements replicating the structure iOS expects & programmatically click.

## OS Differences

### iOS 12

- Works in Safari & PWA's (e.g. pages saved to the Home Screen from Safari)
- Doesn't work in in-app browsers

### iOS 13

- Works in all of the above
- Now works in in-app browsers! 🎉
    - Big exception: as of 2019-10-02 this still doesn't work in Facebook's in-app web browser. It looks like they're not using the standard in-app browser & using their own WKWebView ¯\_(ツ)_/¯

## Did I miss anything?

I'm posting this because I'd like there to be a single reference for all this information — if I missed anything, feel free to email me at
<a href="mailto:hi+usdz@cwe.wtf">hi+usdz@cwe.wtf</a> &amp; I'll add it here!

## Acknowledgments
Thanks to [Chris Joel](https://twitter.com/0xcda7a), [Jordan Santell](https://twitter.com/jsantell), & [Josh Carpenter](https://twitter.com/joshcarpenter) for chatting with me about USDZ's on the web & reviewing the first couple drafts of this post!
