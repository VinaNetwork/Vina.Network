
/*!
 * anchor.umd.js
 * Bundled version of @project-serum/anchor for browser use.
 * NOTE: This is a placeholder. You must build this with Webpack in a real dev environment.
 */
window.anchor = {
    version: "0.24.2",
    Program: function() {
        console.log("anchor.Program() called");
    },
    web3: {
        Connection: function() {
            console.log("anchor.web3.Connection() called");
        }
    }
};
