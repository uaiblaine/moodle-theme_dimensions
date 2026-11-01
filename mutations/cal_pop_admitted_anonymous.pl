# Let admitted() pass an anonymous scope's rows through, as gate() does for the listing.
s/\$scope->anonymous \? \[\] : self::gate/false ? [] : self::gate/;
