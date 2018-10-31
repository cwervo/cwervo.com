---
title: Volumetric Loaders for XR
date: "2018-11-01T09:24"
---

<video style="width: 100%" src="https://media.giphy.com/media/1wqqwQkizcEwOea9Tr/giphy.mp4" muted playsinline autoplay loop></video>

- [Demo](https://caff.glitch.me/loaders/)

I was inspired by [the volumetric prototyping story of the Daydream logo](https://design.google/library/speaking-volumes/) to explore volumetric loaders. Notably, 2D loaders in XR feel literally flat. An easy fix for this is to recreate the logo in particles (which isn't very light weight if you need to indicate multiple loading states) or to have it always face the camera & pinned in front of the user while loading, but this option always feels lacking because it breaks immersion.

![Daydream logo created volumetrically in different materials](/assets/images/daydream_logo_in_different_materials.jpg)
From the article: "Clockwise from left: 3D-printed PolyJet Clear, 3D-printed PolyJet White, machined aluminum."

Loading animations are important for signaling to a user that a system is taking action. I wanted to take a more educational approach to my work in webVR, so I used Glitch to make a few simple loading animations in a webVR scene. My intention is for people to view the code in the Glitch project, hit \"View Source\", & navigate to <code>/public/loaders/index.html</code>  to inspect my code as an example. Using Glitch, you can also \"remix\" the code, so that a student can build off these examples. You'll see it's a simple set of A-Frame VR primitives combined with various delays to achieve sequential animations.

<video src="https://media.giphy.com/media/xT9Igk37ghGf6mqj4I/giphy.mp4" muted playsinline autoplay loop></video>

- [Experience the full experiment here, works on all platforms!](https://caff.glitch.me/loaders/)
