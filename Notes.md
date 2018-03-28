Typical Response From UpdateState:
```
pos 0  1  2  3  4  5  6  7  8  9 10
   66 01 24 39 21 0a ff 00 00 01 99
    |  |  |  |  |  |  |  |  |  |  |
    |  |  |  |  |  |  |  |  |  |  checksum
    |  |  |  |  |  |  |  |  |  warmwhite
    |  |  |  |  |  |  |  |  blue
    |  |  |  |  |  |  |  green
    |  |  |  |  |  |  red
    |  |  |  |  |  speed: 0f = highest f0 is lowest
    |  |  |  |  <don't know yet>
    |  |  |  preset pattern
    |  |  off(23)/on(24)
    |  type
    msg head

response from a 5-channel LEDENET controller:
pos 0  1  2  3  4  5  6  7  8  9 10 11 12 13
   81 25 23 61 21 06 38 05 06 f9 01 00 0f 9d
    |  |  |  |  |  |  |  |  |  |  |  |  |  |
    |  |  |  |  |  |  |  |  |  |  |  |  |  checksum
    |  |  |  |  |  |  |  |  |  |  |  |  color mode (f0 colors were set, 0f whites, 00 all were set)
    |  |  |  |  |  |  |  |  |  |  |  cold-white
    |  |  |  |  |  |  |  |  |  |  <don't know yet>
    |  |  |  |  |  |  |  |  |  warmwhite
    |  |  |  |  |  |  |  |  blue
    |  |  |  |  |  |  |  green
    |  |  |  |  |  |  red
    |  |  |  |  |  speed: 0f = highest f0 is lowest
    |  |  |  |  <don't know yet>
    |  |  |  preset pattern
    |  |  off(23)/on(24)
    |  type
    msg head
```



Typical call to the Bulb:
```
sample message for original LEDENET protocol (w/o checksum at end)
 0  1  2  3  4
56 90 fa 77 aa
 |  |  |  |  |
 |  |  |  |  terminator
 |  |  |  blue
 |  |  green
 |  red
 head

sample message for 8-byte protocols (w/ checksum at end)
 0  1  2  3  4  5  6
31 90 fa 77 00 00 0f
 |  |  |  |  |  |  |
 |  |  |  |  |  |  terminator
 |  |  |  |  |  write mask / white2 (see below)
 |  |  |  |  white
 |  |  |  blue
 |  |  green
 |  red
 persistence (31 for true / 41 for false)

byte 5 can have different values depending on the type
of device:
For devices that support 2 types of white value (warm and cold
white) this value is the cold white value. These use the LEDENET
protocol. If a second value is not given, reuse the first white value.

For devices that cannot set both rbg and white values at the same time
(including devices that only support white) this value
specifies if this command is to set white value (0f) or the rgb
value (f0).

For all other rgb and rgbw devices, the value is 00

sample message for 9-byte LEDENET protocol (w/ checksum at end)
 0  1  2  3  4  5  6  7
31 bc c1 ff 00 00 f0 0f
 |  |  |  |  |  |  |  |
 |  |  |  |  |  |  |  terminator
 |  |  |  |  |  |  write mode (f0 colors, 0f whites, 00 colors & whites)
 |  |  |  |  |  cold white
 |  |  |  |  warm white
 |  |  |  blue
 |  |  green
 |  red
 persistence (31 for true / 41 for false)

```