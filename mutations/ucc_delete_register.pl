# Drop the registration the evidence partial carried: no handler listens for the delete trigger on this page.
s/\n\s*EvidenceDelete\.register\('\[data-action="delete-evidence"\]', '\[data-region="evidence"\]'\);//;
