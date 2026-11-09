# Draw the step's image with Boost Union's card view off.
s/const image = covers \? safeImage\(step\.image\) : '';/const image = safeImage(step.image);/;
