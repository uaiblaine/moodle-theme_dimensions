# Hear a failure offered and never say it was taken: the visitor's panel shows the inline error.
s/            event\.detail\.taken = client\.unanswered\(event\.detail\.poster, event\.detail\.psort\);/            client.unanswered(event.detail.poster, event.detail.psort);/;
