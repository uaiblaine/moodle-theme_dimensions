# The category state link is for whoever may change the category's state.
s/\n                && has_capability\(self::CAP_CATEGORYSTATE, \$unlisted->get_context\(\)\)//;
