---
title: Dynamicland & me
layout: layouts/base.pug
padding: true
---

<style>
video, img {
    max-width: 90%;
}

.image-group {
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: center;
}

.image-group img {
    width: 50%;
}

.image-group.three img {
    display: inline-block;
    width: 31%;
}

#fingersVideo {
    width: 210px;
    height: 315px;
}

@media (min-width: 600px) {
    #fingersVideo {
        width: 420px;
        height: 630px;
    }
}
</style>

# Dynamicland & me

The work on this page requires a little context. These projects were all made at [Dynamicland](https://dynamicland.org/). The space uses cameras, projectors, and a custom language to facilitate turning the environment (walls, tables, anything you can stick a piece of paper onto) into a programmable space. Really, though, the research is more focused on the space, and the people: what kinds of interactions can you have when computing is easy, accessible, and ubiquitous? It's about democratized control & access, through the lens of computation. For more detail I'd encourage you to read [Omar Rizwan's project-based explanation of the space](https://rsnous.com/posts/notes-from-dynamicland-geokit/). He goes a little into Realtalk, the superset of Lua that Dynamicland is built with. It's a research lab started by Bret Victor, an intentional community, a gathering space, a learning space. Here are some things I've made there in the context of research, education, art, and exploration.

---

## Table of Contents:

- [Spatially editing programs with my fingers](./#spatially-editing-programs-with-my-fingers)
- [Live patching numbers in Realtalk programs to generate random variations](./#live-patching-numbers-in-realtalk-programs-to-generate-random-variations)
- [3D red/cyan stereoscopic projections!](./#3d-red%2Fcyan-stereoscopic-projections!)
- [Iambicland](./#iambicland)
- [Patterns between points](./#patterns-between-points)
- [Dials as interfaces in Dynamicland](./#dials-as-interfaces-in-dynamicland)
- [3D-Tracking-In-Dynamicland](./#3d-tracking-in-dynamicland)

---

## Spatially editing programs with my fingers

<iframe id="fingersVideo" src="https://www.youtube-nocookie.com/embed/PYaZ7dZWEBc" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>

The video above demonstrates how the inherently spatial design of the programming system paired with live editing makes for a powerful combination. First, some context:

- There are a few spatial interface primitives built into Realtalk OS, one of which is the concept of "dots": small solid groups of color. Every program can access the dots Realtalk sees in its quadrilateral, each of which has a [LAB color](https://en.wikipedia.org/wiki/CIELAB_color_space) and `x`, `y` coordinates.
- I often keep [my nails painted a two-toned blue](https://www.target.com/p/sally-hansen-complete-salon-manicure-black-and-blue/-/A-14368647). As it turns out Realtalk will see my nails as a blue/brown dot, this has been taken advantage of in this experiment using dots as control inputs.
    - See [this video](https://twitter.com/acwervo/status/1036142903993618432) for a short demonstration of painted fingernails as dots, as well as how we can enlarge the quadrilateral simply by cutting the paper & moving the corners to create a bigger surface.
- Each Realtalk program is stored in a shared database, but can be patched (edited) in realtime, & reverted to its cannonical state at any time (erasing the patch).

So, in the video linked above I used my nails as dot input to a program that can live edit the numbers in a Realtalk program (specifically, whichever number on the page of the program is closest to a given dot). With this powerful chain of simple tools in Realtalk I alter the color, radius & stroke width of the circle (the output of the patched program) using only my fingers.
- Note: this was based off a "number scrubber" program [Bret Victor](http://worrydream.com/)—the principal investigator of the lab—originally wrote.

 <video src="https://media.giphy.com/media/jUhfjIriaO8MNGlvyy/giphy.mp4" autoplay playsinline muted loop></video>


## Live patching numbers in Realtalk programs to generate random variations
- [example 1](https://twitter.com/acwervo/status/1020854349302509574)
- [example 2](https://twitter.com/acwervo/status/1022342475329884160)
- Motivating question: can we spatialize genetic programming?
- Further, could we use a spatial interface & negotiation between steps in the randomization to produce a system for influencing genetic programming in a way that's inaccessible when you cannot be "inside" the computer as with Dynamicland?

The prototype I created takes a page it's pointing at & randomizes all the numbers in the program. I'm working toward being able to chain or show various branches of randomly chosen numbers for the program, with a hope this this could be:

1. an accessible way to explain genetic programming
2. as well as present an interface for people familiar with genetic programming to embody the process & be able to literally point to what variation bias they want to reproduce between generations.

![randomized circle program running](/assets/images/dynamicland/random_program.gif)

## _Iambicland_

- [Video link](https://twitter.com/acwervo/status/990650486779883520)
- A pun that turned into an interesting little experiment using small cubes to specify a number, specifically what [Shakespeare sonnet](http://shakespeare.mit.edu/Poetry/sonnets.html) you'd like to read (from a limited corpus of 10, but could be extended to the full 154).

![Iambicland screenshot](/assets/images/dynamicland/iambicland_video_screenshot.png)

## 3D red/cyan stereoscopic projections!

Originally I was inspired to work with [anaglyph projection](https://en.wikipedia.org/wiki/Anaglyph_3D) in Dynamicland to get a slight 3D effect on the 2D programs. Then, I got distracted by a much more novel concept: can you use just the red & blue glasses to hide UI in Dynamicland? This is interesting because everything in Dynamicland is, on principle, public & thus shared information. This could be a model for a kind of opt-in privacy! First, I started by swapping the blue/red lenses in two pairs of glasses:

<div class="image-group three">
<img src="/assets/images/dynamicland/red_blue_glasses.jpg" alt="">
<img src="/assets/images/dynamicland/resulting_glasses.jpg" alt="">
</div>
<div class="caption">Preparing the glasses. (left) Completed single-channel glasses. (right)</div>

When you put on the single-channel color glasses the opposing color light effectively disappears:

<div class="image-group three">
<img src="/assets/images/dynamicland/vision_normal.jpg" alt="">
<img src="/assets/images/dynamicland/vision_blue.jpg" alt="">
<img src="/assets/images/dynamicland/vision_red.jpg" alt="">
</div>
<div class="caption">Original, red-only, & blue-only versions of dots with their corresponding colors highlighted.</div>

<div class="image-group three">
<img src="/assets/images/dynamicland/necker_original.jpg" alt="">
<img src="/assets/images/dynamicland/necker_blue.jpg" alt="">
<img src="/assets/images/dynamicland/necker_red.jpg" alt="">
</div>
<div class="caption">Original, red-only, & blue-only versions of a pair of necker cubes.</div>

Note that the necker cubes above disappear almost completely while the blue/red dots, because they're opaque objects & not just projected light, are less well hidden. Designing experiences for a game or private messaging application in this way could be an interesting way to keep all Dynamicland programs public by default but facilitate privacy & privileged information when necessary. Future work in this direction would include using gray-light frequency-shift keying (FSK) to track & encode messages using publicly available projectors. This would build on the technique outlined in [_Moveable Interactive Projected Displays Using Projector Based Tracking_](http://johnnylee.net/academic/p104-lee.pdf) (Johnny C. Lee, et al.).

I wrapped up this experiment & left it to the community with this little illustration:

<img style="max-width: 33%; text-align: center;" src="/assets/images/dynamicland/cover_for_box.jpg" alt="">

## Dials as interfaces in Dynamicland

One of the most powerful consequences of Realtalk having first class concepts of space in the language is that a lot of things we'd normally have to simulate come "for free" in the physical world. A good example of this is rotation: instead of calculating translations to simulate rotation we can stick pages on turntables ("[lazy susans](https://en.wikipedia.org/wiki/Lazy_Susan)") & rotate them for real. [Ricardo Cabello](https://mrdoob.com/) used [`atan2()`](https://en.wikipedia.org/wiki/Atan2) to calculate the rotation of the paper using a corner of the program's quadrilateral against the center. With this method we get dials that control everything from text spacing, to animations, to music. Some examples I've developed:

- [Controlling text spacing with a physical dial](https://twitter.com/acwervo/status/1020740175939350529)

 <video src="https://media.giphy.com/media/lfgyWyL6eTi4RDZ7F6/giphy.mp4" autoplay playsinline muted loop></video>

- [Controlling multiple animations with one dial](https://twitter.com/acwervo/status/1020740175939350529), demonstrating Realtalk `When` broadcasting in practice
- [Multiple animations + a description of the communal nature of developing in Dynamicland](https://twitter.com/acwervo/status/983107535312797697)

 <video src="https://media.giphy.com/media/3oqgJb4oEpdjjzIWb3/giphy.mp4" autoplay playsinline muted loop></video>

- [Height-based "dial"](https://twitter.com/acwervo/status/985360740113465345)
    - This program simply declares itself a `dial`, but the way it calculates its value is novel. It compares the relative length of the page with the width of the `supporter` (in general this is the larger surface that's being projected on, in the video it's a table, but can be a wall, the floor, etc.) Normally this length is constant because the paper is resting on the supporter surface (in fact, the project mapping depends on this fact, which is why you'll notice the labelled value (~1.3) appears to drift in the video above - we could calculate the drift based the amount the paper is raised), but when you raise the page off the table, assuming at least one corner of dots its still visible to the camera, this value changes. We can use this as a proxy value for height from the table & normalize this to produce a reliable height-based "dial" (more accurately called a slider perhaps?)


 <video src="https://media.giphy.com/media/58FrrF6qsKJdFDVZSw/giphy.mp4" autoplay playsinline muted loop></video>

## 3D Tracking In Dynamicland

All the other experiments above save the height-base dial, and indeed most programs in Dynamicland, are spatial but naturally limited to 2D because it's an easier starting point than 3D (nearly trivial projection mapping, easy spatial input tracking with an RGB camera, etc.) Along with [Toby Schachman](http://tobyschachman.com/), one of the researchers, I've been exploring using ultra-high frequency (UHF) RFID to get low-cost 3D tracking to be accessible in Dynamicland.
There are no visuals for now but we can get a stream of UHF RFID signals into Realtalk as JSON, the next natural step is to transform that into Lua tables with which to trigger/animate programs.

