# Assert that the film is already running when the full screen control is pressed, which makes the
# adoption beside it unreachable. Two readers reach that control with the film stopped - nothing
# auto-plays under prefers-reduced-motion, and nothing auto-plays on a touch device, where the
# cluster is on screen permanently and this button sits beside the play one - and both of them then
# get full screen on a black rectangle. Coming back out is the worse half: the browser's own bar is
# put on whatever is full screen, so the film is played from THERE, and on the way out the bar is
# removed while the film keeps running with `playing` naming some other band. The still is painted
# over it, and onOut(), onFocusOut() and the IntersectionObserver all test `playing === band`, so
# nothing on the page can stop it again.
#
# The pattern is the one condition, never the block, and it is spelled as a named local for exactly
# that reason: `if (playing !== band)` also opens a branch of toggle(), and a pattern that matched
# both would be a gate whose target moves the next time the functions are reordered.
s/const running = playing === band && !video\.paused;/const running = true;/;
