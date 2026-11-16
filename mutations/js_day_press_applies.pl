# Make a press of a day a draft again: the period moves on the boxes and nothing is told.
s/tell\(State\.press\(state\.current, iso\)\);/update(State.press(state.current, iso).model);/;
