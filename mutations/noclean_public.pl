# Skip HTMLPurifier on the public page too: stored script in a course summary then
# executes for every visitor of a public landing page.
s/'noclean' => !\$this->ispublic,/'noclean' => true,/;
