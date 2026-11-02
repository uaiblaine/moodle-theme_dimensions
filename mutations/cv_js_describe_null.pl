# Hand the trusted sink a feeder on the visitor's page.
s/                    describe=\{null\}/                    describe={(id: number) => Promise.resolve(String(id))}/;
