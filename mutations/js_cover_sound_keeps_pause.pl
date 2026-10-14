# Make the sound control start a film that is paused in place, which is the other half of what a
# finger met. The reader pressed pause to hold that frame; a control that resumed the film takes
# away the thing they stopped to see, and on a touch screen the two controls sit eight pixels apart
# in a cluster that never goes away, so it is the likeliest press of the two to be made by mistake.
# start() also remutes, so the press that asked for sound produces a film playing silently from
# wherever it stood - a control that reports the opposite of what it did.
#
# The branch itself stays: a card AT REST must still start on this press, because the press is the
# user gesture a browser wants before anything audible plays and start() mutes whatever it starts,
# so a glyph on its own would mean nothing.
#
# The pattern is the CONDITION, anchored on its own function's opening line: the same condition
# opens a branch of toggle() above it, and -0777 replaces the first match in the file. The wildcard
# between the two absorbs anything inserted at the head of sound().
s/(const sound = \(band\) => \{.*?)if \(playing !== band\) \{/${1}if (playing !== band || video.paused) {/s;
