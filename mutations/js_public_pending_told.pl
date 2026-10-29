# Tell the posters no question is out: their numbers are drawn over the old answer.
s/document\.dispatchEvent\(new CustomEvent\(LISTING\.pending, \{detail: \{filtered: Listing\.narrows\(state\)\}\}\)\);/void Listing.narrows(state);/;
