---
title: Everything I Know About Launching iOS QuickLook From HTML
date: "2019-09-24"
draft: true
includeModelViewer0dot6: true
syntaxHighlighting: true
---

Did you know you can launch 3D models from a web page? Like this:

<model-viewer ar src="/assets/3D-models/logo.glb" ios-src="/assets/3D-models/logo-3m-scaled.usdz" auto-rotate camera-controls background-color="#2EAFAC"></model-viewer>
<div class="caption">A 3D version of my personal logo made using <a href="https://github.com/GoogleWebComponents/model-viewer"><code>&lt;model-viewer&gt;</code></a>
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

![AR QuickLook Preview](/assets/images/arquicklook-blog-post/directness-test-screenshot.png)

Okay, that's cool! How does it work? Well under the hood this web component — [`<model-viewer>`](https://github.com/GoogleWebComponents/model-viewer) — is using browser-specific attributes to launch proprietary AR features (translation: this will only work in specific browsers & is not standard HTML!). On iOS devices it's using  [iOS QuickLook](https://developer.apple.com/augmented-reality/quick-look/) & on Android it's using [Android's AR Scene Viewer](https://developers.google.com/ar/develop/java/scene-viewer). While Google documents exactly how Scene Viewer should be launched from HTML, Apple's HTML QuickLook documentation is super sparse. It's not on [their documentation website](https://developer.apple.com/documentation/) & all I could find officially documenting it is:

- A talk from 2018 — [Integrating Apps and Content with AR Quick Look](https://developer.apple.com/videos/play/wwdc2018/603/)
- A talk from 2019 — [Advances in AR Quick Look](https://developer.apple.com/videos/play/wwdc2019/612)
- [Four slides in this PDF](https://devstreaming-cdn.apple.com/videos/wwdc/2018/603augiuv41xoowslk8/603/603_integrating_apps_and_content_with_ar_quick_look.pdf) (21 through 25, if you're curious)

... and that's it! There's a [wealth](https://developer.apple.com/design/human-interface-guidelines/ios/system-capabilities/quick-look/). [of](https://developer.apple.com/library/archive/documentation/FileManagement/Conceptual/DocumentInteraction_TopicsForIOS/Introduction/Introduction.html). [information](https://developer.apple.com/documentation/quicklook). on the app-based way to launch QuickLook, but surprisingly little on how to launch it from the web.

## Everything I know

### The Image Tag Is Required

One thing they don't *explicitly* say is that the image tag is **required**. You can't just make a link with `href="eg.usdz"` & `rel=ar` — it needs to have a direct child that's an image.That is, in HTML, you *need* this structure:

    <a href="example.usdz" rel="ar">
      <img href="example-poster" />
    </a>

### The Image Tag _Must_ Be The First Child

I ran a [little test]() & as of 2019-09-24, on iOS 13, it appears

![AR QuickLook Preview](/assets/images/arquicklook-blog-post/directness-test-screenshot.png)
<div class="caption">A screenshot from <a href="https://test-ios-quicklook-js.glitch.me/">test-ios-quicklook-js.glitch.me</a> showing that from HTML the only way to activate AR QuickLook on iOS is to have the direct, first child of a <code>rel=ar</code> element be an <code>img</code></div>

## Launching QuickLook From JavaScript
What if you don't want to use a preview image? You can use the link standalone in HTML like this:

```html
<a href="example.usdz" rel="ar">
    <img height="1" width="1" />
    <!-- Put whatever else you want in here -->
</a>
```

## OS Differences

### iOS 12

- Works in Safari & PWA's (e.g. pages saved to the Home Screen from Safari)
- Doesn't work in WKWebViews

### iOS 13

- Works in all of the above
- Big exception: as of 2019-09-23 this still doesn't work in Facebook's in-app web browser. It looks like they're not using the standard in-app browser & using their own WKWebView ¯\_(ツ)_/¯

Include that this works in in-app browsers as of iOS 13 (e.g. Twitter's link preview or Notion's preview. Not Facebooks's though because they're using a custom WKWebView) & in PWA'S. Doesn't in iOS 12.

/// try [this](https://twitter.com/hvhbinc/status/1176277202985467906?s=21) on old iPhone !! See if it works in Notion's webview & Twitter's Webview!

// test this in a Glitch project that sets itself as a minimal iOS PWA

## Did I miss anything?

I'm posting this because I'd like there to be a single reference for all this information — if I missed anything, feel free to email me at hi+usdz@cwe.wtf

// note: this means you have to set up mail forwarding from [cwe.wtf](http://cwe.wtf) ... See if Cloudflare can do this? Otherwise just use namecheap mail service 🙃
