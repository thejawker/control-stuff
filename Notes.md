Typical Response From UpdateState:
```
pos  0  1  2  3  4  5  6  7  8  9 10
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
pos  0  1  2  3  4  5  6  7  8  9 10 11 12 13
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