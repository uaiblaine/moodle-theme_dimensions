# Answer a window no client asks for.
s/if \(!publiccache::canonical\(/if (false \&\& !publiccache::canonical(/;
