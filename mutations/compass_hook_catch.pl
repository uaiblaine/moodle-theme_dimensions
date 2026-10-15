# Catch nothing the answer can throw, as if the try/catch were gone: a fault behind the crests then
# reaches block_compass's dispatch instead of being reported and answered as nothing.
s/catch \(\\Throwable \$exception\)/catch (\\theme_dimensions_never_thrown \$exception)/;
