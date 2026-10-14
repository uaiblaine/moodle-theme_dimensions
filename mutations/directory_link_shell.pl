# Spell every directory card's address with the logged-in shell, whichever shell is drawing.
# It is invisible while the root is not public: on the anonymous root each card then points at
# /course/index.php, which answers a
# visitor with the login page or bounces them through the forward, so the one page a visitor
# reaches leads nowhere.
s/'url' => links::showcase\(\$childid, \[\], \$scope->anonymous\)->out\(false\),/'url' => links::showcase(\$childid, [], false)->out(false),/;
