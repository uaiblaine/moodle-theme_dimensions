# Put the favourites back into the state chip's vocabulary: the listing service then admits
# chip=favourites, which narrows nothing any more, and the remembered state stores it as a chip.
s/return self::chips\(\);/return array_merge(self::chips(), [self::CHIP_FAVOURITES]);/;
