# Let a finger's LIFT stop a film. A touch pointer has
# no hover state: the browser reports a pointerover as the finger touches down and a pointerout as
# it lifts, and the lift arrives BEFORE the click it belongs to. With this guard broken, onOut()
# reads that lift as "the pointer left the card", pauses the film, rewinds it and sets `playing` to
# null - so the press that follows a fraction of a second later lands on a card at rest, and both
# controls take their starting branch: the play control restarts the film instead of pausing it and
# the sound control restarts it instead of only unmuting it, which leaves tapping the picture
# itself as the only way to stop anything.
#
# The entering half (onOver) is deliberately left alone: hoverable() already refuses to START a
# film for a coarse pointer, so a mutation there reddens nothing a reader would ever meet. This is
# the leaving half, which nothing refused.
#
# The pattern is the CONDITION, anchored on its own function's opening line because the same
# condition opens onOver() twelve lines above and -0777 replaces the first match in the file. The
# wildcard between the two is what keeps a line added to the head of onOut() from killing it, and
# it is the same technique js_cover_fullscreen_adopts uses by naming a local.
s/(const onOut = \(event\) => \{.*?)if \(!hovers\(event\)\) \{/${1}if (false) {/s;
