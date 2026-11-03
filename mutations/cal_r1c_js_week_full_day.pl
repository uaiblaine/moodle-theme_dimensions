# The week's items are placed in the busy band while the body draws the whole day: they land at the wrong hours.
s/const box = hourBox\(laid, look\.zone, FULL_DAY\);/const box = hourBox(laid, look.zone, plan.window || FULL_DAY);/;
