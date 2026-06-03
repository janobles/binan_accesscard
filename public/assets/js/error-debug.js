// Legacy generic tab-switcher and element toggle utility.
// Manages a set of <li>/<a> tab links bound to content <div> IDs via href hash.
// The toggle() function shows/hides any element by ID.
// Note: this is older pre-Bootstrap code kept for any views that still reference
// the bare init() / showTab() / toggle() globals.
// Connected to: any view that calls init() on DOMContentLoaded or toggle() inline.
var tabLinks = new Array();
var contentDivs = new Array();

function init()
{
    var tabListItems = document.getElementById('tabs').childNodes;

    for (var i = 0; i < tabListItems.length; i ++)
    {
        if (tabListItems[i].nodeName == "LI")
        {
            var tabLink = getFirstChildWithTagName(tabListItems[i], 'A');
            var id = getHash(tabLink.getAttribute('href'));
            tabLinks[id] = tabLink;
            contentDivs[id] = document.getElementById(id);
        }
    }

    var linkIndex = 0;

    for (var linkId in tabLinks)
    {
        tabLinks[linkId].onclick = showTab;
        tabLinks[linkId].onfocus = function () {
            this.blur()
        };
        if (linkIndex == 0)
        {
            tabLinks[linkId].className = 'active';
        }
        linkIndex ++;
    }

    var contentIndex = 0;

    for (var contentId in contentDivs)
    {
        if (contentIndex != 0)
        {
            contentDivs[contentId].className = 'content hide';
        }
        contentIndex ++;
    }
}

function showTab()
{
    var selectedId = getHash(this.getAttribute('href'));

    for (var id in contentDivs)
    {
        if (id == selectedId)
        {
            tabLinks[id].className = 'active';
            contentDivs[id].className = 'content';
        }
        else
        {
            tabLinks[id].className = '';
            contentDivs[id].className = 'content hide';
        }
    }

    return false;
}

function getFirstChildWithTagName(element, tagName)
{
    for (var i = 0; i < element.childNodes.length; i ++)
    {
        if (element.childNodes[i].nodeName == tagName)
        {
            return element.childNodes[i];
        }
    }
}

function getHash(url)
{
    var hashPos = url.lastIndexOf('#');
    return url.substring(hashPos + 1);
}

function toggle(elem)
{
    elem = document.getElementById(elem);

    if (elem.style && elem.style['display'])
    {
        var disp = elem.style['display'];
    }
    else if (elem.currentStyle)
    {
        var disp = elem.currentStyle['display'];
    }
    else if (window.getComputedStyle)
    {
        var disp = document.defaultView.getComputedStyle(elem, null).getPropertyValue('display');
    }

    elem.style.display = disp == 'block' ? 'none' : 'block';

    return false;
}
