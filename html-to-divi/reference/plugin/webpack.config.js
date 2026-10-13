const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = {
  mode: process.env.NODE_ENV === 'development' ? 'development' : 'production',

  entry: {
    bundle: './src/index.js',
  },

  // Divi's Visual Builder already enqueues these; they live on `window` and
  // must NOT be bundled. Mapping matches Divi's own extension example.
  externals: {
    jquery: 'jQuery',
    underscore: '_',
    lodash: 'lodash',
    react: ['vendor', 'React'],
    'react-dom': ['vendor', 'ReactDOM'],

    '@wordpress/i18n': ['vendor', 'wp', 'i18n'],
    '@wordpress/hooks': ['vendor', 'wp', 'hooks'],

    '@divi/rest': ['divi', 'rest'],
    '@divi/data': ['divi', 'data'],
    '@divi/module': ['divi', 'module'],
    '@divi/module-utils': ['divi', 'moduleUtils'],
    '@divi/modal': ['divi', 'modal'],
    '@divi/field-library': ['divi', 'fieldLibrary'],
    '@divi/icon-library': ['divi', 'iconLibrary'],
    '@divi/module-library': ['divi', 'moduleLibrary'],
    '@divi/style-library': ['divi', 'styleLibrary'],
  },

  module: {
    rules: [
      {
        test: /\.jsx?$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            compact: false,
            presets: [
              ['@babel/preset-env', { modules: false, targets: '> 5%' }],
              '@babel/preset-react',
            ],
          },
        },
      },
      {
        test: /\.css$/i,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              url: false,
              importLoaders: 1,
            },
          },
        ],
      },
    ],
  },

  optimization: {
    // `style.css` files go to the VB-only bundle; everything else to the
    // front-end bundle. Same split Divi's example extension uses.
    splitChunks: {
      cacheGroups: {
        vb: {
          type: 'css/mini-extract',
          test: /[\\/]style(\.module)?\.(sc|sa|c)ss$/,
          chunks: 'all',
          enforce: true,
          name(_, chunks, cacheGroupKey) {
            const chunkName = chunks[0].name;
            return `${path.dirname(chunkName)}/${cacheGroupKey}-${path.basename(chunkName)}`;
          },
        },
        default: false,
      },
    },
  },

  plugins: [
    new MiniCssExtractPlugin({
      filename: '../styles/[name].css',
    }),

    // Divi's PHP side reads module metadata from `modules-json/<slug>/`.
    new CopyWebpackPlugin({
      patterns: [
        {
          from: '**/module.json',
          context: 'src/components',
          to: path.resolve(__dirname, 'modules-json'),
        },
        {
          from: '**/module-default-render-attributes.json',
          context: 'src/components',
          to: path.resolve(__dirname, 'modules-json'),
        },
      ],
    }),
  ],

  resolve: {
    extensions: ['.js', '.jsx', '.json'],
  },

  output: {
    filename: '[name].js',
    path: path.resolve(__dirname, 'scripts'),
  },

  stats: {
    errorDetails: true,
  },
};
