# Draw the login button for every visitor, as before stage 21.
s/'showlogin' => \$state === resolution::STATE_VISITOR && \$this->resolution->loginwayin,/'showlogin' => \$state === resolution::STATE_VISITOR,/
