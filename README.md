# The YES3 EM Log Parameter Viewer  

## The problem addressed

REDCap's built in EM Log browser is very powerful, but it may truncate the displayed value of the EM Log Message, or the value of an EM Log Parameter. This is reasonable since these values can be extremely large. However, there are times when viewing the full content is desirable.

## What it does

YES3 EM Log Parameter Viewer enhances REDCap's built-in EM Log browser by allowing you to display the full content of the log message, and of any em log parameter.

## Getting started  

After the EM is installed, it will be available on the Control Center ```view logs``` link, for use by REDCap Admins. The EM may also be enabled for any project, for use by REDCap Project staff.

### Required permissions

You must have permissions sufficient to manage external modules to access this EM. For Control Center access the required administrator permission is "Install, upgrade and configure External Modules". For project access, design permission is required.

## How to view truncated EM Log content  

Open the 'View Logs' link on the **External Modules** panel, either on the Control Center page or on a project page for which the EM has been enabled. You will see a screen something like the following:  

<img src="./images/emlpv00.png" width="800px" /> 

### Displaying the full content of a truncated EM Log Message

To view the full content of an EM Log Message, click on any cell in the EM Log View ```Message``` column. 

### Displaying the full content of a truncated EM Log Message Parameter  

Click on any ```Show Parameters``` button to open a ```Log Entry Parameters``` dialog, on which are displayed the names and (possibly truncated) values of parameters associated with the selected EM Log Record:  

<img src="./images/emlpv01.png" width="800px" />  

Clicking on a displayed value on the REDCap EM Log View Parameters dialog will open a new dialog (below) that will display the complete value of the selected log parameter, along with other information about the log record. The parameter content is displayed in a scrolling container.  

Here is an example:  

<img src="./images/emlpv02.png" width="800px" />  

## Technical Note      
   
The YES3 EM Log Parameter Viewer uses a somewhat wonky algorithm to fetch messages and parameter values, and there is a very low - possibly astronomically low - probability that the wrong value will be be retrieved and displayed. The imprecise record-matching algorithm is necessary because the EM log record key cannot be determined from the REDCap EM Log View Document Object Model (DOM). The fix is in the queue for the REDCap Framework Team, and when this issue is addressed, I will update the YES3 EM Log Parameter Viewer to use the new information for exact matching.

Peter Charpentier, 28 January 2026.  
