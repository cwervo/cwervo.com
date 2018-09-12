---
title: Gestalt Based Programming
date: 2018-09-01
---

## Lessons from Dynamicland, Lego, Logo, and Photshop.

The seed of this idea began when I started playing around with [Baku89](https://twitter.com/_baku89)'s lovely [pen tool](https://s.baku89.com/pentool/):

<figure>
    <img src="../../assets/images/gestalt-based-programming/baku_pentool.gif" alt="Baku pen tool">
    <figcaption>pen tool demonstration</figcaption>
</figure>

There's a lot going on here:

- you can draw using a selected tool
- you can see the code the current tool is using (& edit & update it!)
- you can dynamically update the parameters of tool (e.g. color)
- (not shown) maybe most exciting, you can make your own tool/brush just by clicking the `+` button!

I sent this to [Geoffrey Litt](http://geoffreylitt.com/)—who also thinks about
the intersection of programming languages/tools/interface design—and he
responded with some really interesting observations, the most illuminating one
for me is quoted below:

> The Pen Tool demo is fascinating. It's truly "open source" since it actively
> encourages the user to add new code and remix the software.. feels like
> taking a small part of the Dynamicland ethos and making it available in a
> more traditional environment. Even if the source for the entire site isn't
> open, I prefer this sort of thoughtful openness to "I threw the source on
> Github, you can try to read it if you want"...  I've seen kids hit the
> "remix" button in Scratch before and be totally overwhelmed by seeing 100s of
> blocks, it would be much better if they could have a nice guided onramp into
> modding an existing program.

<figure>
<img src="../../assets/images/scratch-programming-interface-gaming.jpg" alt="Scratch example">
<figcaption> <a href="https://opensource.com/article/18/4/designing-game-scratch-open-jam">Complex Scratch programs</a> can get intimidatingly large.  </figcaption>
</figure>


That last bit, about Scratch being intimidating when kids go to remix (also
known as "fork") a program and they aren't expecting 100's of blocks, was the
most profound for me: there's something about the way Scratch's "simple" but
verbose editing experience exposes all the internals of the system that's
terrifying, that feels like a bad abstraction. In complete contrast, this pen
tool seems more inviting—"a nice guided onramp" in Geoffrey's words—exactly
because it's hiding a bunch of unnecessary complexity. Much like
[Processing](https://processing.org/)/[P5.js](https://p5js.org/) and
[OpenFrameworks](https://openframeworks.cc/)—creative coding programming
tools—this tool starts you off with only `press()`, `drag()`, & `release()`
functions, it abstracts the rest away into a drawing context; that is, it's a
programming tool that takes advantage of the concept of _gestalt_.

---

## Gestalt? What's that?

> Gestalt (noun): _something that is made of many parts and yet is somehow more than or different from the combination of its parts_ ([Merriam-Webster](https://www.merriam-webster.com/dictionary/gestalt))

When I use _gestalt_ in this post I mean it with the definition above. "Gestalt
based programming"[^1] is less
about programming than it is about the interface between
programming & its environment(s) (the editor, the runtime, the GUI, the OS, the
VCS, etc.). Specifically, a gestalt based programming system has abstractions
that expose complexity in a way that treats the complexity of the environment
as a whole, hidden away until you actually need to deal with it (say, when
you're building a kernel or writing a low-level graphics engine).

You might object to my comparison above between the pen tool and Scratch
because they're different kinds of tools: one's a programming environment, the
other's explicitly a drawing application with programming _functionality_. So let's compare it to a different example: Photoshop.

[You can script
Photoshop](https://www.adobe.com/devnet/photoshop/scripting.html) using a
custom subset/extension of Javascript, AppleScript, or VBScript. You load
scripts into a special directory in the app & then it creates a button that
runs that script. So far this sounds like the pen tool experience above!
Unfortunately, it's not directly analogous: Photoshop scripts run
synchronously, blocking the app while they run, and you can only interact with
them via dialog boxes (e.g. "What color should this tint be?"). The pen tool
above programs brushes, which runs every time a `mousepress` is registered,
meaning it's interacted with more naturally & can subsume the function of
Photoshop scripts: I could, theoretically, detect a double tap and use that to
kick off a background process like a PS script rather than draw. This all comes
down to the goal of user-enabled programs between the two drawing tools: in
Photoshop this is a clunky, expert-user tool, so it's assumed users here will
be fine grokking the entire Photoshop document & editing model (not gestalt,
very much needing to think about individual bits & pieces in sequential order
to perform actions), whereas the pen tool was designed to be an extensible
programmatic brush so the user interface can be programmed simply as one
system, allowing for an art-making experience that's infinitely more customizable.

---

## Logo, Scratch, Dynamicland

<div style="color: red">Todo: explain Logo, Scratch, & Dynamicland in the
lineage of "extendible programming environments", since the quote above relies on
knowing this a bit? At least link to Omar's piece about DL & quickly explain
Logo/Scratch connection & philosophies??</div>

----

## Parting thoughts

_not_ gestalt-y | gestalt-y
--- | ---
Scratch | Logo
Photoshop | Baku89's pen tool
Github | ???
programming languages with implicit environments (most of them) | Lisp, Smalltalk (explicit environments)


For a tight feedback loop between interfaces & programming systems take advantage of the gestalt instead of having every abstraction expose its internals "simply" (other way to put it: clarity > transparency when designing a system/language/API).

<!-- <div style="background&#45;color: #DAD"> -->
<!-- <img src="/assets/images/favicon.png" alt=""> -->
<!-- <img src="/assets/images/favicon&#45;white.png" alt=""> -->
<!-- <img src="/assets/images/circles_teal.svg" alt=""> -->
<!-- </div> -->

[^1]: though I made the term up mostly as a [joke on
Twitter](https://twitter.com/acwervo/status/1036014674754433027)
