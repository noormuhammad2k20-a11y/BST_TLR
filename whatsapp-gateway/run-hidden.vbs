' Starts the gateway with no visible window.
'
' The scheduled task calls this instead of the .bat so nothing pops up on the
' screen when Windows starts. Output still goes to gateway.log.

Dim shell, fso, here
Set shell = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")

here = fso.GetParentFolderName(WScript.ScriptFullName)
shell.CurrentDirectory = here

' 0 = hidden window, False = do not wait for it to finish
shell.Run "cmd /c node """ & here & "\server.js""", 0, False
