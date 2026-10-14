# Let a film inherit the sound state of the card before it instead of starting muted. Every hover
# would then play whatever the last press left behind - and because browsers refuse to autoplay an
# unmuted film, the usual outcome is not noise but a still that never moves, which reads as a
# broken preview rather than as a rule that was dropped.
#
# The pattern is the one assignment, never the block: start() is where a fresh play begins and
# resume() is deliberately the path that does NOT remute.
s/video\.muted = true;/video.muted = false;/;
