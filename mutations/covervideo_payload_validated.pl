# Name a cover video in the cached payload whatever its bytes are, so a card links a file the
# route refuses: a control that looks broken rather than a still that simply never plays. The
# route's own check is the guard beside this one; the two together are what make "a video is
# never trusted by its name" true of a page as well as of a request.
s/'covervideo' => \(\$video !== null && \$videotype !== null\) \?/'covervideo' => (\$video !== null) ?/;
