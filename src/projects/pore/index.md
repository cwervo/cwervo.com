---
title: PORE
layout: layouts/base.pug
padding: true;
---

<style>
video {
max-width: 80vw;
}
@media (min-width: 700px) { video { max-width: 40vw; } }
</style>

# PORE: a communal VR art installation

On June 2nd & 3rd of 2018 I was in Berlin, showing people onto an exercise mat & placing them in a VR art experience.

I was invited to make a VR art piece for the [JSConf EU](https://2018.jsconf.eu/) Community Lounge. [Sage Jenson](https://sagejenson.com/) created the sound (background music) & photogrammetry (concrete tube surrounding the participant & a scan of the exercise mat that participants were standing on in the venue). I created the webVR installation using A-Frame, as well as a realtime database to allow people not in VR to view the experience being built live as particpants interacted with the piece (this was a convenient side-effect of streaming gaze data to a database for later processing).

The experience itself consisted of viewers looking at a sequence of neck stretches (slower than shown below), prompted to follow these neck stretches & ruminate on the taxing physical nature of VR. The hope was that we could create an experience in VR—a medium usually associated with escapism, and in fact total erasure of your body, a kind of digital blindfold—that actual caused you to become *more* aware of your body, not less.

<video src="https://media.giphy.com/media/OPvBZUqbFfK3heQ12u/giphy.mp4" loop playsinline autoplay muted></video>

As they'd look around partipants would draw a line 10 meters away from their head, in the direction of their gaze. In the actual experience this line was colorerd with a blue-to-yellow depth based shader.

<video src="https://media.giphy.com/media/fWghmiy4aoVTd2D86a/giphy.mp4" loop playsinline autoplay muted></video>

<video src="https://media.giphy.com/media/kF5P2E2zmZCNFplD0X/giphy.mp4" loop playsinline autoplay muted></video>


The gaze data, collected from ~40 particpants over the two days of the conference, was collected & visualized as a single path using Three.js. The final version is still viewable [on the installation website](https://andrescuervo.github.io/jsconfeu-2018). You can see artifacts from the prompts:
- many people created this cross/plus shape toward the front of the circle as a result of the left/right, up/down neck stretches
- there's exactly one person (corresponding to one line in the visualization) that goes completely around the radius of the sphere—this means _most_ participants only viewed what was right in front of them

Some day I may 3D print or visualize this data more, but it's interesting enough for now to have a collective physical sculpture created communally  by participants in the installation.

<video src="https://media.giphy.com/media/PMTN6Ewo7Ms4B3O64n/giphy.mp4" loop playsinline autoplay muted></video>

---

## An initial prototype of the path tracing
<video src="https://media.giphy.com/media/7IYLBJ47XjMnIEGHHT/giphy.mp4" loop playsinline autoplay muted></video>
