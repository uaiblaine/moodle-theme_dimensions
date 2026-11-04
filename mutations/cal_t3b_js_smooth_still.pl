# Animate a scroll for a reader who asked for less motion.
s/behavior: smooth && !still\(\) \? 'smooth' : 'auto'/behavior: smooth ? 'smooth' : 'auto'/;
