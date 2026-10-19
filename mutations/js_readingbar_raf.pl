# Decide on every scroll event, outside the animation frame.
s/bar\.frame = window\.requestAnimationFrame\(decide\);/decide();/;
