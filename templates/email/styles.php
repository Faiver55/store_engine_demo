<?php
/**
 * Email Styles
 *
 * This template can be overridden by copying it to yourtheme/storeengine/emails/styles.php.
 *
 * @package StoreEngine\Templates\Emails
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// !important; is a gmail hack to prevent styles being stripped if it doesn't like something.
// body{padding: 0;} ensures proper scale/positioning of the email in the iOS native email app.
?>
img {
	border: none;
	-ms-interpolation-mode: bicubic;
	max-width: 100%;
}

body {
	background-color: #f6f6f6;
	width: 100%;
	font-family: sans-serif;
	-webkit-font-smoothing: antialiased;
	font-size: 14px;
	line-height: 1.4;
	margin: 0;
	padding: 0;
	-ms-text-size-adjust: 100%;
	-webkit-text-size-adjust: 100%;
}

/* Set a max-width, and make it display as block so it will automatically stretch to that width, but will also shrink down on a phone or something */
.container {
	display: block;
	margin: 0 auto !important;
	/* makes it centered */
	max-width: 580px;
	padding: 10px;
	margin: 55px auto;
	width: 580px;
	background: white;
}

/* This should also be a block element, so that it will fill 100% of the .container */
.content {
	box-sizing: border-box;
	display: block;
	margin: 0 auto;
	max-width: 580px;
	padding: 45px;
}

/* HEADER, FOOTER, MAIN */
.main {
	background: #ffffff;
	border-radius: 3px;
	width: 100%;
}

.wrapper {
	box-sizing: border-box;
}
.wrapper p {
	font-size: 14px;
	margin-bottom: 10px;
}
.wrapper .entry-button {
	display: flex;
	column-gap: 15px;
}

.content-block {
	padding-bottom: 10px;
	padding-top: 10px;
}

.footer {
	clear: both;
	margin-top: 10px;
	width: 100%;
	margin-top: 30px;
	font-size: 15px;
}

.footer p,
.footer span,
.footer a {
	color: #999999;
}

/* TYPOGRAPHY */
h1,
h2,
h3,
h4,
h5,
h6 {
	color: #000000;
	font-family: sans-serif;
	font-weight: 500;
	line-height: 1.4;
	margin: 0;
	margin-bottom: 10px;
}

h1 {
	font-size: 35px;
}

p,
ul,
ol {
	font-family: sans-serif;
	font-size: 14px;
	font-weight: normal;
	margin: 0;
	margin-bottom: 15px;
}

p li,
ul li,
ol li {
	list-style-position: inside;
	margin-left: 5px;
}

a {
	color: #3498db;
	text-decoration: underline;
}

/* BUTTONS */

.btn-primary,
.btn-secondary {
	box-sizing: border-box;
	display: inline-block;
	text-decoration: none;
	font-size: 14px;
	padding: 12px 30px;
	background: #7B68EE;
	color: #FFFFFF !important;
	border-radius: 6px;
	font-weight: normal;
}
.btn-secondary {
	background: #EAEBEE;
	color: #0A083A !important;
}

h5.main-heading {
	font-size: 21px;
	margin-bottom: 25px;
}

/* OTHER STYLES THAT MIGHT BE USEFUL */
.last {
	margin-bottom: 0;
}

.first {
	margin-top: 0;
}

.align-center {
	text-align: center;
}

.align-right {
	text-align: right;
}

.align-left {
	text-align: left;
}

.clear {
	clear: both;
}

.mt0 {
	margin-top: 0;
}

.mb0 {
	margin-bottom: 0;
}

.preheader {
	color: transparent;
	display: none;
	height: 0;
	max-height: 0;
	max-width: 0;
	opacity: 0;
	overflow: hidden;
	mso-hide: all;
	visibility: hidden;
	width: 0;
}

.powered-by a {
	text-decoration: none;
}

hr {
	border: 0;
	border-bottom: 1px solid #f6f6f6;
	margin: 20px 0;
}

.ql-align-center {
	text-align: left;
}
.ql-align-center {
	text-align: center;
}
.ql-align-right {
	text-align: right;
}
.ql-align-justify {
	text-align: justify;
}


ol li.ql-indent-1 {
	counter-increment: list-1
}

ol li.ql-indent-1:before {
	content: counter(list-1,lower-alpha) ". "
}

ol li.ql-indent-1 {
	counter-reset: list-2 list-3 list-4 list-5 list-6 list-7 list-8 list-9
}

ol li.ql-indent-2 {
	counter-increment: list-2
}

ol li.ql-indent-2:before {
	content: counter(list-2,lower-roman) ". "
}

ol li.ql-indent-2 {
	counter-reset: list-3 list-4 list-5 list-6 list-7 list-8 list-9
}

ol li.ql-indent-3 {
	counter-increment: list-3
}

ol li.ql-indent-3:before {
	content: counter(list-3,decimal) ". "
}

ol li.ql-indent-3 {
	counter-reset: list-4 list-5 list-6 list-7 list-8 list-9
}

ol li.ql-indent-4 {
	counter-increment: list-4
}

ol li.ql-indent-4:before {
	content: counter(list-4,lower-alpha) ". "
}

ol li.ql-indent-4 {
	counter-reset: list-5 list-6 list-7 list-8 list-9
}

ol li.ql-indent-5 {
	counter-increment: list-5
}

ol li.ql-indent-5:before {
	content: counter(list-5,lower-roman) ". "
}

ol li.ql-indent-5 {
	counter-reset: list-6 list-7 list-8 list-9
}

ol li.ql-indent-6 {
	counter-increment: list-6
}

ol li.ql-indent-6:before {
	content: counter(list-6,decimal) ". "
}

ol li.ql-indent-6 {
	counter-reset: list-7 list-8 list-9
}

ol li.ql-indent-7 {
	counter-increment: list-7
}

ol li.ql-indent-7:before {
	content: counter(list-7,lower-alpha) ". "
}

ol li.ql-indent-7 {
	counter-reset: list-8 list-9
}

ol li.ql-indent-8 {
	counter-increment: list-8
}

ol li.ql-indent-8:before {
	content: counter(list-8,lower-roman) ". "
}

ol li.ql-indent-8 {
	counter-reset: list-9
}

ol li.ql-indent-9 {
	counter-increment: list-9
}

ol li.ql-indent-9:before {
	content: counter(list-9,decimal) ". "
}

.ql-indent-1:not(.ql-direction-rtl) {
	padding-left: 3em
}

li.ql-indent-1:not(.ql-direction-rtl) {
	padding-left: 4.5em
}

.ql-indent-1.ql-direction-rtl.ql-align-right {
	padding-right: 3em
}

li.ql-indent-1.ql-direction-rtl.ql-align-right {
	padding-right: 4.5em
}

.ql-indent-2:not(.ql-direction-rtl) {
	padding-left: 6em
}

li.ql-indent-2:not(.ql-direction-rtl) {
	padding-left: 7.5em
}

.ql-indent-2.ql-direction-rtl.ql-align-right {
	padding-right: 6em
}

li.ql-indent-2.ql-direction-rtl.ql-align-right {
	padding-right: 7.5em
}

.ql-indent-3:not(.ql-direction-rtl) {
	padding-left: 9em
}

li.ql-indent-3:not(.ql-direction-rtl) {
	padding-left: 10.5em
}

.ql-indent-3.ql-direction-rtl.ql-align-right {
	padding-right: 9em
}

li.ql-indent-3.ql-direction-rtl.ql-align-right {
	padding-right: 10.5em
}

.ql-indent-4:not(.ql-direction-rtl) {
	padding-left: 12em
}

li.ql-indent-4:not(.ql-direction-rtl) {
	padding-left: 13.5em
}

.ql-indent-4.ql-direction-rtl.ql-align-right {
	padding-right: 12em
}

li.ql-indent-4.ql-direction-rtl.ql-align-right {
	padding-right: 13.5em
}

.ql-indent-5:not(.ql-direction-rtl) {
	padding-left: 15em
}

li.ql-indent-5:not(.ql-direction-rtl) {
	padding-left: 16.5em
}

.ql-indent-5.ql-direction-rtl.ql-align-right {
	padding-right: 15em
}

li.ql-indent-5.ql-direction-rtl.ql-align-right {
	padding-right: 16.5em
}

.ql-indent-6:not(.ql-direction-rtl) {
	padding-left: 18em
}

li.ql-indent-6:not(.ql-direction-rtl) {
	padding-left: 19.5em
}

.ql-indent-6.ql-direction-rtl.ql-align-right {
	padding-right: 18em
}

li.ql-indent-6.ql-direction-rtl.ql-align-right {
	padding-right: 19.5em
}

.ql-indent-7:not(.ql-direction-rtl) {
	padding-left: 21em
}

li.ql-indent-7:not(.ql-direction-rtl) {
	padding-left: 22.5em
}

.ql-indent-7.ql-direction-rtl.ql-align-right {
	padding-right: 21em
}

li.ql-indent-7.ql-direction-rtl.ql-align-right {
	padding-right: 22.5em
}

.ql-indent-8:not(.ql-direction-rtl) {
	padding-left: 24em
}

li.ql-indent-8:not(.ql-direction-rtl) {
	padding-left: 25.5em
}

.ql-indent-8.ql-direction-rtl.ql-align-right {
	padding-right: 24em
}

li.ql-indent-8.ql-direction-rtl.ql-align-right {
	padding-right: 25.5em
}

.ql-indent-9:not(.ql-direction-rtl) {
	padding-left: 27em
}

li.ql-indent-9:not(.ql-direction-rtl) {
	padding-left: 28.5em
}

.ql-indent-9.ql-direction-rtl.ql-align-right {
	padding-right: 27em
}

li.ql-indent-9.ql-direction-rtl.ql-align-right {
	padding-right: 28.5em
}
<?php
