---
title: Volumetric Loaders for XR
date: "2018-11-01T09:24"
syntaxHighlighting: true
---

<style> video { width: 100%; } </style>

<video src="https://media.giphy.com/media/1wqqwQkizcEwOea9Tr/giphy.mp4" muted playsinline autoplay loop></video>

- [Demo](https://caff.glitch.me/loaders/)

I was inspired by [the volumetric prototyping story of the Daydream logo](https://design.google/library/speaking-volumes/) to explore volumetric loaders for XR. The problem is pretty straight forward: 2D loaders in XR feel literally flat. One fix for this is to recreate the logo in particles but this isn't very lightweight if you need to indicate multiple loading states. A more common solution is to have a 2D quad always face the camera & pinned in front of the user while loading, but this option too feels lacking—namely, it breaks the immersion of the experience.

![Daydream logo created volumetrically in different materials](/assets/images/daydream_logo_in_different_materials.jpg)
<div class="caption">The Daydream design team used physical prototypes of the logo shape to test its properties. From the article: "Clockwise from left: 3D-printed PolyJet Clear, 3D-printed PolyJet White, machined aluminum."
</div>

Loading animations are important for signaling to a user that a system is taking action if we can't achieve it in realtime. Not having to make a user wait through loading would be the _ideal_ scenario but because that isn't always possible in practice it's worth taking a look at loading indicators in XR. I wanted to take a more educational approach to my work in webVR, so I used Glitch to make a few simple loading animations in a webVR scene. My intention is for people to [take a look at the Glitch project](https://glitch.com/~caff), hit \"View Source\", & read the code in <code>/public/loaders/index.html</code> as examples of ways of building loaders by combining primitives. Using Glitch you can also \"remix\" the code, making it easy to build off of these examples for your own learning.

You'll see each loader is a collection of [A-Frame](https://aframe.io) VR primitives combined with various animations, most using delays to achieve sequential animations. The [Pug](https://pugjs.org/api/getting-started.html) snippet below is for the first loader, a ring of spheres with a circular scale delay rippling across them. This generates HTML, which is transformed by A-Frame into WebGL. If you'd like to know more about each loader [go take a look at the code](https://glitch.com/edit/#!/caff?path=public/loaders/index.html:32:0), maybe try making some volumetric loaders yourself!

```pug
a-entity(position="0 0 0" rotation="0 90 0" layout=`type: circle; radius: 0.25;`)
    - var duration = 1000
    - var maxSpheres = 10
    - var n = 0
    while n < maxSpheres
        - var initialScale = 0.025
        - var targetScale = 0.07
        a-sphere(
        color=`hsl(${n * 10 + 200}, 100%, 80%)`
        scale=`${initialScale} ${initialScale} ${initialScale};`
        animation__scale=`property: scale; from: ${initialScale} ${initialScale} ${initialScale};
        to: ${targetScale} ${targetScale} ${targetScale}; delay: ${(duration / maxSpheres) * n}; dir: alternate; loop: true; easing: easeInOutSine; dur: ${duration}`
        )
        - n++
```

- [Experience the full experiment here, works on all platforms!](https://caff.glitch.me/loaders/)

<video src="https://media.giphy.com/media/xT9Igk37ghGf6mqj4I/giphy.mp4" muted playsinline autoplay loop></video>
