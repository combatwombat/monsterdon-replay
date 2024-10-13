#!/bin/bash

TIMEFORMAT='done in %3Rs'

function build_sass {
  echo -n "building sass... "
  time sass scss:../../public/css --style compressed
}

function build_js {

  input_files=(
      #"js/libs/htmx.js"
      #"js/libs/htmx.preload.js"
      #"js/libs/alpine.collapse.js"
      #"js/libs/alpine.min.js"
      "js/web-components/x-timeline.js"
      "js/libs/helper.js"
      "js/main.js"
  )

  output_file="../../public/js/main.js"
  output_file_compressed="../../public/js/main.min.js"

  echo -n "building js... "

  # compressed, minified
  time terser --source-map "filename='main.js.map'" --compress --output $output_file_compressed "${input_files[@]}"

  # uncompressed, pretty printed. just concatenate them
  time cat "${input_files[@]}" > $output_file

}

if [ "$1" == "sass" ]; then
    build_sass
elif [ "$1" == "js" ]; then
    build_js
else
    build_sass
    build_js
fi








