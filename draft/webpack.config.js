const path = require('path');

module.exports = {
  mode: 'production',
  entry: './public_html/accounts/acc.js',
  output: {
    path: path.resolve(__dirname, 'public_html/accounts'),
    filename: 'acc.bundle.js'
  },
  resolve: {
    extensions: ['.js', '.jsx']
  },
  module: {
    rules: [
      {
        test: /\.jsx?$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env', '@babel/preset-react']
          }
        }
      }
    ]
  }
};
