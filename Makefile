# Hot Tracks — local helpers for App Store packaging
APP_ID := hot_tracks

.PHONY: build appstore clean

build:
	npm ci
	npm run build

appstore: build
	bash scripts/package-appstore.sh

clean:
	rm -rf build
