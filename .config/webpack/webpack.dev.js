const { merge } = require("webpack-merge");
const common = require("./webpack.common.js");

const miniCssExtractPlugin = require("mini-css-extract-plugin");
const fixStyleOnlyEntriesPlugin = require("webpack-fix-style-only-entries");

const plugins = () => [
  new miniCssExtractPlugin({
    filename: "[name].css",
  }),
  new fixStyleOnlyEntriesPlugin(),
];

module.exports = merge(common, {
  mode: "development",
  devtool: "cheap-source-map",
  plugins: plugins(),
});
