# Draw the logged-in page's context for a visitor.
s/        if \(\$this->visitor\) \{\n            return \$this->visitor_context/        if (false) {\n            return \$this->visitor_context/;
