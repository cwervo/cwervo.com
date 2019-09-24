---
title: Everything I Know About Launching iOS QuickLook From The Web
date: "2019-09-24"
draft: true
includeModelViewer0dot6: true
syntaxHighlighting: true
---

Did you know you can launch 3D models from a web page? There's a helpful web component called <a href="https://github.com/GoogleWebComponents/model-viewer"><code>&lt;model-viewer&gt;</code></a> that will handle this for you. It'll look something like this:

<model-viewer ar src="/assets/3D-models/logo.glb" ios-src="/assets/3D-models/logo-3m-scaled.usdz" auto-rotate camera-controls background-color="#2EAFAC" alt="Spinning AC logo"></model-viewer>
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

On iOS it even launches a proprietary feature called AR QuickLook:

![AR QuickLook screenshot, previewing 3D personal logo](/assets/images/arquicklook-blog-post/logo-quicklock-screencap.jpg)


Okay, that's cool! How does it work? Well under the hood this web component — [`<model-viewer>`](https://github.com/GoogleWebComponents/model-viewer) — is using browser-specific attributes to launch proprietary AR features (translation: this will only work in specific browsers & is not standard HTML!) On iOS devices it's using  [iOS QuickLook](https://developer.apple.com/augmented-reality/quick-look/) & on Android it's using [Android's AR Scene Viewer](https://developers.google.com/ar/develop/java/scene-viewer). While Google documents exactly how Scene Viewer should be launched from HTML, Apple's HTML QuickLook documentation is super sparse. It's not on [their documentation website](https://developer.apple.com/documentation/) & all I could find officially documenting it is:

- A few minutes in a WWDC talk from 2018 — [Integrating Apps and Content with AR Quick Look](https://developer.apple.com/videos/play/wwdc2018/603/)
- A few minutes in a follow up WWDC talk from 2019 — [Advances in AR Quick Look](https://developer.apple.com/videos/play/wwdc2019/612)
- [Four slides in this PDF](https://devstreaming-cdn.apple.com/videos/wwdc/2018/603augiuv41xoowslk8/603/603_integrating_apps_and_content_with_ar_quick_look.pdf) (21 through 25, if you're curious)

... and that's it! There's a [wealth](https://developer.apple.com/design/human-interface-guidelines/ios/system-capabilities/quick-look/). [of](https://developer.apple.com/library/archive/documentation/FileManagement/Conceptual/DocumentInteraction_TopicsForIOS/Introduction/Introduction.html). [information](https://developer.apple.com/documentation/quicklook). on the app-based way to launch QuickLook, but surprisingly little on how to launch it from the web. This post exists because I've learned a bunch about launching iOS QuickLook from the browser while using it <a href="http://movableink.com/" target="blank_">at work</a> & I want to save others time!

## Everything I know

### The Image Tag Is Required

One thing they don't *explicitly* say is that the image tag is **required**. You can't just make a link with `href="eg.usdz"` & `rel=ar` — it needs to have a direct child that's an image.That is, in HTML, you *need* this structure:

```html
<a href="example.usdz" rel="ar">
    <img href="eg.jpg>" />
</a>
```

### The Image Tag _Must_ Be The First Child

I ran a [little test]() & as of 2019-09-24, on iOS 13, it appears that it's a strict requirement for the `img` tag to be the first nested child of the `rel=ar` link element. If it's the second element, or even nested in the first child element, Safari fails to recognize it as an AR QuickLook element & instead will link to the USDZ directly. You can see this because the special QuickLook box icon doesn't appear in the top right of any elements after the first:

![AR QuickLook Preview](/assets/images/arquicklook-blog-post/directness-test-screenshot.png)
<div class="caption">A screenshot from <a href="https://test-ios-quicklook-js.glitch.me/">test-ios-quicklook-js.glitch.me</a> showing that from HTML the only way to activate AR QuickLook on iOS is to have the direct, first child of a <code>rel=ar</code> element be an <code>img</code></div>

What if you don't want to use a preview image? You can actually get away with something like this:

```html
<a href="example.usdz" rel="ar">
    <img /> <!-- remember, this has to be first! -->
    <!-- Put whatever else you want in here -->
</a>
```

If you're on iOS 13 right now try <a href="https://cwervo-assets.netlify.com/models/cwervo/logo.usdz" rel="ar"> <img> <span>hitting this piece of text</span> </a> & you should see AR QuickLook pop up with the model from the beginning of the post!

## Launching QuickLook From JavaScript


On my [Test iOS QuickLook page](https://glitch.com/~test-ios-quicklook-js) I have a function called `launchIOSQuickLookAR` that's the minimum JavaScript you need to launch iOS AR QuickLook

```javascript
// `usdzSrc` is expected to be a string path to a `.usdz` file
function launchIOSQuickLookAR(usdzSrc) {
  const anchor = document.createElement('a');
  anchor.setAttribute('rel', 'ar');
  anchor.appendChild(document.createElement('img'));
  anchor.setAttribute('href', usdzSrc);
  anchor.click();
}
```
<div class="caption">Note: this code is basically a modified version of the <a href="https://github.com/GoogleWebComponents/model-viewer/blob/master/src/features/ar.ts#L27-L36"> <code>openIOSARQuickLook</code></a> in <code>&lt;model-viewer&gt;</code></div>

Yep! There's not API for it — all we can do for now is create offscreen HTML elements replicating the structure iOS expects & programmatically click.

## OS Differences

### iOS 12

- Works in Safari & PWA's (e.g. pages saved to the Home Screen from Safari)
- Doesn't work in in-app browsers

### iOS 13

- Works in all of the above
- Now works in in-app browsers! 🎉
    - Big exception: as of 2019-09-23 this still doesn't work in Facebook's in-app web browser. It looks like they're not using the standard in-app browser & using their own WKWebView ¯\_(ツ)_/¯

## Did I miss anything?

I'm posting this because I'd like there to be a single reference for all this information — if I missed anything, feel free to email me at
<a href="mailto:hi+usdz@cwe.wtf">hi+usdz@cwe.wtf</a> &amp; I'll add it here!

## Acknowledgments
Thanks to [Chris Joel](https://twitter.com/0xcda7a) & [Jordan Santell](https://twitter.com/jsantell) for chatting about USDZ's on the web & reviewing the first couple drafts of this post!
