#!/bin/bash

echo "watching 👀"
fswatch -o scss js | xargs -n1 -I{} ./build.sh

