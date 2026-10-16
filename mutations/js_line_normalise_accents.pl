# Keep the accents: "inscricao" no longer finds "Inscrição".
s/\.normalize\('NFD'\)\.replace\(\/\[\\u0300-\\u036f\]\/g, ''\)//;
