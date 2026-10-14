# Read every rejected play() as a refusal again. A second press of the play control while the film
# is still loading pauses it before play() resolves; the browser rejects that play() with an
# AbortError, and stopping on it rewinds the film and forgets it, so the sound control's next press
# meets a card at rest and starts the film the reader had just paused.
s/error && error\.name === 'AbortError' && playing === band && video && video\.paused/false/;
