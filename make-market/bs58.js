/*!
 * bs58 v5.0.0 - https://github.com/cryptocoinjs/bs58
 * License: MIT
 */
(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
  typeof define === 'function' && define.amd ? define(factory) :
  (global.bs58 = factory());
})(this, (function () { 'use strict';

  var BASE = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
  var LEADER = BASE.charAt(0);
  var BASE_MAP = new Uint8Array(256);
  BASE_MAP.fill(255);
  for (var i = 0; i < BASE.length; ++i) {
    BASE_MAP[BASE.charCodeAt(i)] = i;
  }

  function encode(buffer) {
    if (buffer.length === 0) return '';

    var digits = [0];
    for (var i = 0; i < buffer.length; ++i) {
      var carry = buffer[i];
      for (var j = 0; j < digits.length; ++j) {
        carry += digits[j] << 8;
        digits[j] = carry % 58;
        carry = (carry / 58) | 0;
      }
      while (carry) {
        digits.push(carry % 58);
        carry = (carry / 58) | 0;
      }
    }

    var string = '';
    for (var k = 0; buffer[k] === 0 && k < buffer.length - 1; ++k) {
      string += LEADER;
    }
    for (var q = digits.length - 1; q >= 0; --q) {
      string += BASE[digits[q]];
    }
    return string;
  }

  function decode(string) {
    if (string.length === 0) return new Uint8Array(0);

    var bytes = [0];
    for (var i = 0; i < string.length; ++i) {
      var c = string[i];
      var value = BASE_MAP[c.charCodeAt(0)];
      if (value === 255) throw new Error('Invalid character found: ' + c);

      var carry = value;
      for (var j = 0; j < bytes.length; ++j) {
        carry += bytes[j] * 58;
        bytes[j] = carry & 0xff;
        carry >>= 8;
      }
      while (carry) {
        bytes.push(carry & 0xff);
        carry >>= 8;
      }
    }

    for (var k = 0; string[k] === LEADER && k < string.length - 1; ++k) {
      bytes.push(0);
    }

    return new Uint8Array(bytes.reverse());
  }

  return {
    encode: encode,
    decode: decode
  };

}));
