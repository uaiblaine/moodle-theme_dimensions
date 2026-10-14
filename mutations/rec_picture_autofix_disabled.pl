# Offer the automatic fix while the picture type is disabled, when every field only reads as missing
# because the disabled type hides them, and creating them again would duplicate fields.
s/return \$this->installed && \$this->enabled && \$this->missing !== \[\];/return \$this->installed && \$this->missing !== [];/;
