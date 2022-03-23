const Pippin = async function (env, token, successcb, failurecb, dcb) {
	if(dcb){
		Pippin.dismissCallback = dcb
	}
    this.uuid = function () {
        var dt = new Date().getTime();
        var uuid = "x4xxxyxxx".replace(/[xy]/g, function (c) {
            var r = (dt + Math.random() * 16) % 16 | 0;
            dt = Math.floor(dt / 16);
            return (c == "x" ? r : (r & 0x3) | 0x8).toString(16);
        });
        return uuid;
    };
    this.replaceJS = function (filePathToJSScript) {
        return new Promise((resolve, reject) => {
            if (document.getElementById("hljs")) {
                resolve(1);
            }
            var list = document.getElementsByTagName("script");
            var i = list.length,
                flag = false;
            while (i--) {
                if (list[i].src === filePathToJSScript) {
                    flag = true;
                    break;
                }
            }

            if (!flag) {
                var tag = document.createElement("script");

                tag.src = filePathToJSScript;
                tag.setAttribute("id", "hljs");
                document.getElementsByTagName("head")[0].appendChild(tag);

                let script = document.querySelector("#hljs");
                script.addEventListener("load", function () {
                    resolve(1);
                });
            } else {
                resolve(1);
            }
        });
    };
    this.token = token;
    this.failurecb = failurecb;
    this.successcb = successcb;
    this.this_ID = this.uuid();
    this.this_ID_IN = this.this_ID + "_in";
    let preent = "old";
    if (env == "production") {
        preent = await this.replaceJS(
            "https://sdk.cashfree.com/js/magento/1.0.26/dropinClient.prod.js?v=" +
                Date.now()
        );
    } else {
        preent = await this.replaceJS(
            "https://sdk.cashfree.com/js/magento/1.0.26/dropinClient.sandbox.js?v=" +
                Date.now()
        );
    }

    var cx = document.getElementsByClassName("cfgandalf");
    for (let i = 0; i < cx.length; i++) {
        let element = cx[i];
        element.parentNode.removeChild(element);
    }

    let theDiv = document.getElementsByTagName("body")[0];
    let content = document.createElement("div");
    content.setAttribute("id", this.this_ID);
    content.classList.add("cfgandalf");

    let modal = document.createElement("div");
    modal.setAttribute("id", this.this_ID_IN);
    modal.style.height = "100%";

	if (Pippin.isMobile()){
		let closeBar = document.createElement("div");
        closeBar.classList.add("close-bar");
        closeBar.innerHTML =
            "<div style='height:5px; width:40%;display:inline-block;background-color:#D1D9D9;position: absolute;top: 8px; left: 50%; transform: translateX(-50%); border-radius: 5px;'></div>";
        closeBar.style.height = "20px";
        closeBar.setAttribute("id", "close-bar" + this.this_ID_IN);
        modal.append(closeBar);
	}
    

    content.append(modal);
    theDiv.appendChild(content);
	if (document.getElementById("close-bar" + this.this_ID_IN)) {
        Pippin.addSwipeEvent(
            document.getElementById("close-bar" + this.this_ID_IN),
            "swipeDown",
            function () {
                modalx.close();
            }
        );
    }
	
    const modalx = new Pippin.Laugh({
        target: content,
        data: {
            style: {
                coverBackgroundColor: "rgba(0,0,0,.4)",
                borderRadius: "20px",
                width: "100%",
            },
        },
    });
    const dropinConfig = {
        components: ["order-details", "upi", "card", "app", "netbanking"],
        orderToken: token,
        onSuccess: (data) => {
            modalx.close();
            this.successcb(data);
        },
        onFailure: (data) => {
            modalx.close();
            this.failurecb(data);
        },
    };

    modalx.setStyle({
        backgroundColor: "#fff",
    });
    modalx.open(this.this_ID_IN);
	let cashfree_var = new window.Cashfree.Cashfree();
    cashfree_var.initialiseDropin(
        document.getElementById(this.this_ID_IN),
        dropinConfig
    );
	Pippin.isMobile() ? document.getElementById("dropin_frame").style.height = "calc(100% - 20px)" : "";
};
Pippin.Laugh = (function () {
    "use strict";

    function appendNode(node, target) {
        target.appendChild(node);
    }

    function insertNode(node, target, anchor) {
        target.insertBefore(node, anchor);
    }

    function detachNode(node) {
        node.parentNode.removeChild(node);
    }

    function createElement(name) {
        return document.createElement(name);
    }

    function createText(data) {
        return document.createTextNode(data);
    }

    function createComment() {
        return document.createComment("");
    }

    function addEventListener(node, event, handler) {
        node.addEventListener(event, handler, false);
    }

    function removeEventListener(node, event, handler) {
        node.removeEventListener(event, handler, false);
    }

    function setAttribute(node, attribute, value) {
        node.setAttribute(attribute, value);
    }

    function get(key) {
        return key ? this._state[key] : this._state;
    }

    function fire(eventName, data) {
        var handlers =
            eventName in this._handlers && this._handlers[eventName].slice();
        if (!handlers) return;

        for (var i = 0; i < handlers.length; i += 1) {
            handlers[i].call(this, data);
        }
    }

    function observe(key, callback, options) {
        var group =
            options && options.defer
                ? this._observers.pre
                : this._observers.post;

        (group[key] || (group[key] = [])).push(callback);

        if (!options || options.init !== false) {
            callback.__calling = true;
            callback.call(this, this._state[key]);
            callback.__calling = false;
        }

        return {
            cancel: function () {
                var index = group[key].indexOf(callback);
                if (~index) group[key].splice(index, 1);
            },
        };
    }

    function on(eventName, handler) {
        var handlers =
            this._handlers[eventName] || (this._handlers[eventName] = []);
        handlers.push(handler);

        return {
            cancel: function () {
                var index = handlers.indexOf(handler);
                if (~index) handlers.splice(index, 1);
            },
        };
    }

    function set(newState) {
        this._set(newState);
        (this._root || this)._flush();
    }

    function _flush() {
        if (!this._renderHooks) return;

        while (this._renderHooks.length) {
            var hook = this._renderHooks.pop();
            hook.fn.call(hook.context);
        }
    }

    function dispatchObservers(component, group, newState, oldState) {
        for (var key in group) {
            if (!(key in newState)) continue;

            var newValue = newState[key];
            var oldValue = oldState[key];

            if (newValue === oldValue && typeof newValue !== "object") continue;

            var callbacks = group[key];
            if (!callbacks) continue;

            for (var i = 0; i < callbacks.length; i += 1) {
                var callback = callbacks[i];
                if (callback.__calling) continue;

                callback.__calling = true;
                callback.call(component, newValue, oldValue);
                callback.__calling = false;
            }
        }
    }
    var desktopHeight = function(){
		let viewPortHeight = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
		let minHeight = 705;
		if (viewPortHeight < minHeight) {
            return viewPortHeight;
        } else {
            return minHeight;
        }
	}
    var template = (function () {
        var template = {
            data() {
                return {
                    defaultStyle: {
                        height: Pippin.isMobile() ? "100%" : (String(desktopHeight()) + "px") ,
                        width: "70%",
                        coverBackgroundColor: "rgba(0,0,0,.4)",
                        backgroundColor: "#222",
                        borderRadius: Pippin.isMobile() ? "6px" : "0px",
                    },
                    style: null,

                    __active: null,
                    __items: {},
                    __tid: null,
                };
            },
            methods: {
                getItem(id) {
                    return this.get("__items")[id];
                },
                setStyle(style) {
                    return this.set({
                        style: Object.assign(this.get("style"), style),
                    });
                },
                removeStyle(styleProps) {
                    const { style } = this.get();
                    if (typeof styleProps === "string") {
                        styleProps = [styleProps];
                    }

                    styleProps.forEach((p) => delete style[p]);
                    this.set({
                        style,
                    });
                },
                open(id) {
                    if (this.get("__active") || this.get("__tid") !== null) {
                        return;
                    }
                    const { contents } = this.refs;
                    const active = this.get("__items")[id];
                    contents.appendChild(active);
                    this.set({
                        __active: active,
                    });
                },
                close() {
                    const { contents } = this.refs;
                    const { __active } = this.get();
                    this.set({
                        __active: null,
                        __tid: setTimeout(() => {
                            contents.removeChild(__active);
                            this.set({
                                __tid: null,
                            });
							Pippin.dismissCallback();
							
                        }, 500),
                    });
                },
            },
            oncreate() {
                init.call(this);
            },
        };

        function init() {
            if (this.get("style") === null) {
                this.set({
                    style: {},
                });
            }

            this.observe("style", (style) => {
                if (typeof style !== "object" || Array.isArray(style)) {
                    throw new Error("Specify object as `style`");
                }
                this.set({
                    style: Object.assign({}, this.get("defaultStyle"), style),
                });
            });

            (() => {
                const { container } = this.refs;
                const parent = container.parentElement;
                const items = Array.prototype.slice
                    .call(parent.children)
                    .filter((el) => !el.classList.contains("pippin"))
                    .reduce((result, el) => {
                        parent.removeChild(el);
                        el.style.display = "";

                        if (!el.id) {
                            return result;
                        }
                        result[el.id] = el;
                        return result;
                    }, {});
                this.set({
                    __items: items || {},
                });
            })();
        }

        return template;
    })();

    let addedCss = false;

    function addCss() {
        var style = createElement("style");
        if (Pippin.isMobile()) {
            style.textContent =
                "\n  [svelte-2241516264].cover, [svelte-2241516264] .cover {\n    position: fixed;\n    left: 0;\n    top: 0;\n    width: 100%;\n    height: 100%;\n    z-index: -1;\n    -webkit-transition: .6s cubic-bezier(0.86, 0, 0.07, 1);\n    transition: .6s cubic-bezier(0.86, 0, 0.07, 1);\n    opacity: 0;\n  }\n\n  [svelte-2241516264].container, [svelte-2241516264] .container { \n position: fixed;\n    z-index: 4;\n    right: 50%;\n    bottom: 0;\n    -webkit-transform: translateX(50%);\n            transform: translateX(50%);\n    width: " +
                "100%" +
                ";\n    \n    overflow: hidden;\n    -webkit-transition: .6s cubic-bezier(0.86, 0, 0.07, 1);\n    transition: .6s cubic-bezier(0.86, 0, 0.07, 1);\n  }\n\n  [svelte-2241516264].box, [svelte-2241516264] .box {\n  -webkit-transition: .4s cubic-bezier(0.86, 0, 0.07, 1);\n    transition: .4s cubic-bezier(0.86, 0, 0.07, 1);\n    position: absolute;\n    right: 50%;\n    bottom: 0;\n    -webkit-transform: translateX(50%);\n            transform: translateX(50%);\n    -webkit-transition-property: height;\n    transition-property: height;\n  }\n\n  [svelte-2241516264].contents, [svelte-2241516264] .contents {\n    padding: 0em;\n    box-sizing: border-box;\n    -webkit-transition: .4s cubic-bezier(0.86, 0, 0.07, 1);\n    transition: .4s cubic-bezier(0.86, 0, 0.07, 1);\n    overflow: auto;\n    height: 100%;\n  }\n\n  [svelte-2241516264].decorate, [svelte-2241516264] .decorate {\n    position: absolute;\n    height: 1em;\n    width: 1em;\n  }\n  [svelte-2241516264].decorate.left-bottom, [svelte-2241516264] .decorate.left-bottom {\n    left: -.72em;\n    bottom: 0;\n  }\n  [svelte-2241516264].decorate.right-bottom, [svelte-2241516264] .decorate.right-bottom {\n    right: -.72em;\n    bottom: 0;\n    -webkit-transform: rotateY(180deg);\n            transform: rotateY(180deg);\n  }\n";
        } else {
            style.textContent =
                "\n  [svelte-2241516264].cover, [svelte-2241516264] .cover {\n    position: fixed;\n    left: 0;\n    top: 0;\n    width: 100%;\n    height: 100%;\n    z-index: -1;\n    -webkit-transition: .6s cubic-bezier(0.86, 0, 0.07, 1);\n    transition: .6s cubic-bezier(0.86, 0, 0.07, 1);\n    opacity: 0;\n  }\n\n  [svelte-2241516264].container, [svelte-2241516264] .container {\n    position: fixed;\n    z-index: 4;\n    left: 50%;\n    top: 50%;\n    -webkit-transform: translate(-50%, -50%);\n      border-radius: 6px;\n      transform: translate(-50%, -50%);\n    width: " +
                "420px" +
                ";\n    \n    overflow: hidden;\n    -webkit-transition: .6s cubic-bezier(0.86, 0, 0.07, 1);\n    transition: .6s cubic-bezier(0.86, 0, 0.07, 1);\n  }\n\n  [svelte-2241516264].box, [svelte-2241516264] .box {\n    -webkit-transition: .4s cubic-bezier(0.86, 0, 0.07, 1);\n    transition: .4s cubic-bezier(0.86, 0, 0.07, 1);\n    position: absolute;\n    left: 50%;\n    top: 50%;\n    -webkit-transform: translate(-50%, -50%);\n            transform: translate(-50%, -50%);\n    -webkit-transition-property: height;\n    transition-property: height;\n  }\n\n  [svelte-2241516264].contents, [svelte-2241516264] .contents {\n    padding: 0em;\n    box-sizing: border-box;\n    -webkit-transition: .4s cubic-bezier(0.86, 0, 0.07, 1);\n    transition: .4s cubic-bezier(0.86, 0, 0.07, 1);\n    overflow: auto;\n    height: 100%;\n  }\n\n  [svelte-2241516264].decorate, [svelte-2241516264] .decorate {\n    position: absolute;\n    height: 1em;\n    width: 1em;\n  }\n  [svelte-2241516264].decorate.left-bottom, [svelte-2241516264] .decorate.left-bottom {\n    left: -.72em;\n    bottom: 0;\n  }\n  [svelte-2241516264].decorate.right-bottom, [svelte-2241516264] .decorate.right-bottom {\n    right: -.72em;\n    bottom: 0;\n    -webkit-transform: rotateY(180deg);\n            transform: rotateY(180deg);\n  }\n";
        }

        appendNode(style, document.head);

        addedCss = true;
    }

    function renderMainFragment(root, component) {
        var ifBlock_anchor = createComment();

        function getBlock(root) {
            if (root.style) return renderIfBlock_0;
            return null;
        }

        var currentBlock = getBlock(root);
        var ifBlock = currentBlock && currentBlock(root, component);

        return {
            mount: function (target, anchor) {
                insertNode(ifBlock_anchor, target, anchor);
                if (ifBlock)
                    ifBlock.mount(ifBlock_anchor.parentNode, ifBlock_anchor);
            },

            update: function (changed, root) {
                var __tmp;

                var _currentBlock = currentBlock;
                currentBlock = getBlock(root);
                if (_currentBlock === currentBlock && ifBlock) {
                    ifBlock.update(changed, root);
                } else {
                    if (ifBlock) ifBlock.teardown(true);
                    ifBlock = currentBlock && currentBlock(root, component);
                    if (ifBlock)
                        ifBlock.mount(
                            ifBlock_anchor.parentNode,
                            ifBlock_anchor
                        );
                }
            },

            teardown: function (detach) {
                if (ifBlock) ifBlock.teardown(detach);

                if (detach) {
                    detachNode(ifBlock_anchor);
                }
            },
        };
    }

    function renderIfBlock_0(root, component) {
        var div = createElement("div");
        setAttribute(div, "svelte-2241516264", "");
        div.className = "pippin cover";

        function clickHandler(event) {
            component.close();
        }

        addEventListener(div, "click", clickHandler);

        div.style.cssText =
            "\n    opacity: " +
            (root.__active ? 1 : 0) +
            ";\n    -webkit-transition-duration: " +
            (root.__active ? "" : ".3s") +
            ";\n    -webkit-transition-delay: " +
            (root.__active ? "" : ".1s") +
            ";\n    transition-duration: " +
            (root.__active ? "" : ".3s") +
            ";\n    transition-delay: " +
            (root.__active ? "" : ".1s") +
            ";\n    z-index: " +
            (root.__active ? 1 : -1) +
            ";\n    background-color: " +
            root.style.coverBackgroundColor +
            ";\n  ";

        var text = createText("\n  ");

        var div1 = createElement("div");
        setAttribute(div1, "svelte-2241516264", "");
        component.refs.container = div1;
        div1.className = "pippin container";
        div1.style.cssText =
            "\n    height: " +
            (root.__active ? root.style.height : "0") +
            ";\n    -webkit-transition-duration: " +
            (root.__active ? "" : ".3s") +
            ";\n    -webkit-transition-timing-function: " +
            (root.__active ? "" : "cubic-bezier(0.165, 0.84, 0.44, 1)") +
            ";\n    -webkit-transition-delay: " +
            (root.__active ? "" : ".1s") +
            ";\n    transition-duration: " +
            (root.__active ? "" : ".3s") +
            ";\n    transition-timing-function: " +
            (root.__active ? "" : "cubic-bezier(0.165, 0.84, 0.44, 1)") +
            ";\n    transition-delay: " +
            (root.__active ? "" : ".1s") +
            ";\n  ";

        var div2 = createElement("div");
        setAttribute(div2, "svelte-2241516264", "");
        div2.className = "pippin box";
        div2.style.cssText =
            "\n      width: " +
            root.style.width +
            ";\n      height: " +
            (root.__active ? root.style.height : 0) +
            ";\n      background-color: " +
            root.style.backgroundColor +
            ";\n      border-top-left-radius: " +
            root.style.borderRadius +
            ";\n      border-top-right-radius: " +
            root.style.borderRadius +
            ";\n    ";

        appendNode(div2, div1);

        var div3 = createElement("div");
        setAttribute(div3, "svelte-2241516264", "");
        component.refs.contents = div3;
        div3.className = "pippin contents";
        div3.style.cssText =
            "\n        opacity: " + (root.__active ? 1 : 0) + ";\n      ";

        appendNode(div3, div2);

        return {
            mount: function (target, anchor) {
                insertNode(div, target, anchor);
                insertNode(text, target, anchor);
                insertNode(div1, target, anchor);
            },

            update: function (changed, root) {
                var __tmp;

                div.style.cssText =
                    "\n    opacity: " +
                    (root.__active ? 1 : 0) +
                    ";\n    -webkit-transition-duration: " +
                    (root.__active ? "" : ".3s") +
                    ";\n    -webkit-transition-delay: " +
                    (root.__active ? "" : ".1s") +
                    ";\n    transition-duration: " +
                    (root.__active ? "" : ".3s") +
                    ";\n    transition-delay: " +
                    (root.__active ? "" : ".1s") +
                    ";\n    z-index: " +
                    (root.__active ? 1 : -1) +
                    ";\n    background-color: " +
                    root.style.coverBackgroundColor +
                    ";\n  ";

                div1.style.cssText =
                    "\n    height: " +
                    (root.__active ? root.style.height : "0") +
                    ";\n    -webkit-transition-duration: " +
                    (root.__active ? "" : ".3s") +
                    ";\n    -webkit-transition-timing-function: " +
                    (root.__active
                        ? ""
                        : "cubic-bezier(0.165, 0.84, 0.44, 1)") +
                    ";\n    -webkit-transition-delay: " +
                    (root.__active ? "" : ".1s") +
                    ";\n    transition-duration: " +
                    (root.__active ? "" : ".3s") +
                    ";\n    transition-timing-function: " +
                    (root.__active
                        ? ""
                        : "cubic-bezier(0.165, 0.84, 0.44, 1)") +
                    ";\n    transition-delay: " +
                    (root.__active ? "" : ".1s") +
                    ";\n  ";

                div2.style.cssText =
                    "\n      width: " +
                    root.style.width +
                    ";\n      height: " +
                    (root.__active ? root.style.height : 0) +
                    ";\n      background-color: " +
                    root.style.backgroundColor +
                    ";\n      border-top-left-radius: " +
                    root.style.borderRadius +
                    ";\n      border-top-right-radius: " +
                    root.style.borderRadius +
                    ";\n    ";

                div3.style.cssText =
                    "\n        opacity: " +
                    (root.__active ? 1 : 0) +
                    ";\n      ";
            },

            teardown: function (detach) {
                removeEventListener(div, "click", clickHandler);
                if (component.refs.container === div1)
                    component.refs.container = null;
                if (component.refs.contents === div3)
                    component.refs.contents = null;

                if (detach) {
                    detachNode(div);
                    detachNode(text);
                    detachNode(div1);
                }
            },
        };
    }

    function Pippin$1(options) {
        options = options || {};
        this.refs = {};
        this._state = Object.assign(template.data(), options.data);

        this._observers = {
            pre: Object.create(null),
            post: Object.create(null),
        };

        this._handlers = Object.create(null);

        this._root = options._root;
        this._yield = options._yield;

        this._torndown = false;
        if (!addedCss) addCss();

        this._fragment = renderMainFragment(this._state, this);
        if (options.target) this._fragment.mount(options.target, null);

        if (options._root) {
            options._root._renderHooks.push({
                fn: template.oncreate,
                context: this,
            });
        } else {
            template.oncreate.call(this);
        }
    }

    Pippin$1.prototype = template.methods;

    Pippin$1.prototype.get = get;
    Pippin$1.prototype.fire = fire;
    Pippin$1.prototype.observe = observe;
    Pippin$1.prototype.on = on;
    Pippin$1.prototype.set = set;
    Pippin$1.prototype._flush = _flush;

    Pippin$1.prototype._set = function _set(newState) {
        var oldState = this._state;
        this._state = Object.assign({}, oldState, newState);

        dispatchObservers(this, this._observers.pre, newState, oldState);
        if (this._fragment) this._fragment.update(newState, this._state);
        dispatchObservers(this, this._observers.post, newState, oldState);
    };

    Pippin$1.prototype.teardown = Pippin$1.prototype.destroy = function destroy(
        detach
    ) {
        this.fire("teardown");

        this._fragment.teardown(detach !== false);
        this._fragment = null;

        this._state = {};
        this._torndown = true;
    };

    return Pippin$1;
})();

Pippin.addSwipeEvent = function (theDom, eventName, handleEvent) {
    // console.log(theDom, eventName, handleEvent);
    var eStart = 0,
        eEnd = 0;

    theDom.addEventListener(
        "touchstart",
        function (e) {
            switch (eventName) {
                case "swipeDown" | "swipeUp":
                    eStart = e.targetTouches[0].clientY;
                    break;
                case "swipeLeft" | "swipeRight":
                    eStart = e.targetTouches[0].clientX;
                    break;
                default:
                    eStart = e.targetTouches[0].clientY;
                    break;
            }
        },
        false
    );

    theDom.addEventListener(
        "mousedown",
        function (e) {
            switch (eventName) {
                case "swipeDown" | "swipeUp":
                    eStart = e.clientY;
                    break;
                case "swipeLeft" | "swipeRight":
                    eStart = e.clientX;
                    break;
                default:
                    eStart = e.clientY;
                    break;
            }
        },
        false
    );

    theDom.addEventListener(
        "touchmove",
        function (e) {
            e.preventDefault();
        },
        false
    );

    theDom.addEventListener(
        "mousemove",
        function (e) {
            e.preventDefault();
        },
        false
    );

    theDom.addEventListener(
        "touchend",
        function (e) {
            switch (eventName) {
                case "swipeDown" | "swipeUp":
                    eEnd = e.changedTouches[0].clientY;
                    break;
                case "swipeLeft" | "swipeRight":
                    eEnd = e.changedTouches[0].clientX;
                    break;
                default:
                    eEnd = e.changedTouches[0].clientY;
                    break;
            }

            var moveVal = eEnd - eStart;
            var moveAbsVal = Math.abs(moveVal);

            // swipeUp
            if (moveVal < 0 && moveAbsVal > 30 && eventName == "swipeUp") {
                // console.log("swipeUp");
                handleEvent();
            }

            // swipeDown
            if (moveVal > 0 && moveAbsVal > 30 && eventName == "swipeDown") {
                // console.log("swipeDown");
                handleEvent();
            }

            // swipeLeft
            if (moveVal < 0 && moveAbsVal > 30 && eventName == "swipeLeft") {
                // console.log("swipeLeft");
                handleEvent();
            }

            // swipeRight
            if (moveVal > 0 && moveAbsVal > 30 && eventName == "swipeRight") {
                // console.log("swipeRight");
                handleEvent();
            }
        },
        false
    );

    theDom.addEventListener(
        "mouseup",
        function (e) {
            switch (eventName) {
                case "swipeDown" | "swipeUp":
                    eEnd = e.clientY;
                    break;
                case "swipeLeft" | "swipeRight":
                    eEnd = e.clientX;
                    break;
                default:
                    eEnd = e.clientY;
                    break;
            }

            var moveVal = eEnd - eStart;
            var moveAbsVal = Math.abs(moveVal);

            // swipeUp
            if (moveVal < 0 && moveAbsVal > 30 && eventName == "swipeUp") {
                // console.log("swipeUp");
                handleEvent();
            }

            // swipeDown
            if (moveVal > 0 && moveAbsVal > 30 && eventName == "swipeDown") {
                // console.log("swipeDown");
                handleEvent();
            }

            // swipeLeft
            if (moveVal < 0 && moveAbsVal > 30 && eventName == "swipeLeft") {
                // console.log("swipeLeft");
                handleEvent();
            }

            // swipeRight
            if (moveVal > 0 && moveAbsVal > 30 && eventName == "swipeRight") {
                // console.log("swipeRight");
                handleEvent();
            }
        },
        false
    );
};

Pippin.isMobile = function() {
    let win = window,
        doc = document,
        docElem = doc.documentElement,
        body = doc.getElementsByTagName("body")[0],
        x = win.innerWidth || docElem.clientWidth || body.clientWidth,
        y = win.innerHeight || docElem.clientHeight || body.clientHeight;
    return x <= 420;
}
Pippin.dismissCallback = function(){
	return true;
}