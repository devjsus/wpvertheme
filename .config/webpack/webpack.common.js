/**
 * * Webpack configuration.
 */

const path = require("path");
const miniCssExtractPlugin = require("mini-css-extract-plugin");

const BUILD_DIR = path.resolve(__dirname, "../../dist");
const SASS_DIR = path.resolve(__dirname, "../../src/scss");
const JS_DIR = path.resolve(__dirname, "../../src/js");
const TS_DIR = path.resolve(__dirname, "../../src/ts");

const entry = {
  "endrock.styles": SASS_DIR + "/index.scss",
  "endrock.scripts": JS_DIR + "/index.js",
  "endrock.ts-scripts": TS_DIR + "/index.ts",
};

const output = {
  path: BUILD_DIR,
  filename: "[name].js",
};

const rules = [
  {
    test: /\.js$/,
    include: [JS_DIR],
    exclude: /node_modules/,
    use: "babel-loader",
  },
  {
    include: [SASS_DIR],
    test: /\.css$/i,
    use: ["style-loader", "css-loader"],
  },
  {
    include: [SASS_DIR],
    test: /\.scss$/,
    exclude: /node_modules/,
    use: [miniCssExtractPlugin.loader, "css-loader", "sass-loader"],
  },
];

module.exports = {
  entry: entry,

  output: output,

  devtool: "source-map",

  module: {
    rules: rules,
  },

  performance: {
    hints: false,
    maxEntrypointSize: 512000,
    maxAssetSize: 512000,
  },
};

