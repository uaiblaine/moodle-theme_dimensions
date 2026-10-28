# Never tell the posters a question is out: their pills keep the last numbers while the answer is on its way.
s/document\.dispatchEvent\(new CustomEvent\(EVENTS\.pending, \{detail: \{filtered: Listing\.narrows\(state\)\}\}\)\);/void Listing.narrows(state);/;
