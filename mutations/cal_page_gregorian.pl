# Serve a viewer on another calendar type, whom the range refuses.
s/            window::require_gregorian\(\);\n        \} catch \(refusal \$refusal\) \{\n            return new self\(self::core_calendar\(\), null\);/            \$unused = 0;\n        } catch (refusal \$refusal) {\n            return new self(self::core_calendar(), null);/;
