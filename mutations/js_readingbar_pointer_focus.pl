# Hold the bar for any focus in it: closing the course index from the layer leaves the bar stuck until a press elsewhere.
s/return focused\.matches\(':focus-visible'\);/return true;/;
